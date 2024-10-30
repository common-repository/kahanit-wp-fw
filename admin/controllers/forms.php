<?php
class Kahanit_WP_FW_Forms
{
	public function get_forms()
	{
		global $wpdb, $kwfdb;

		$start = (isset($_GET['start']) && !empty($_GET['start'])) ? $_GET['start'] : 0;
		$limit = (isset($_GET['limit']) && !empty($_GET['limit'])) ? $_GET['limit'] : 10;
		$load = (isset($_GET['load']) && !empty($_GET['load'])) ? $_GET['load'] : 'false';
		$sort = (isset($_GET['sort']) && !empty($_GET['sort'])) ? json_decode(stripslashes($_GET['sort'])) : '';
		$sort = $sort[0];

		$select = $kwfdb->select()->from(array('s' => $wpdb->prefix . 'kwf_forms'), array('*'))->joinLeft(array('ff' => $wpdb->prefix .
				'kwf_form_fields'), 's.ID = ff.form_ID', array('ff.arguments as field'));

		if (is_object($sort))
		{
			if ($sort->property == 'id')
			{
				$select = $select->order(array('s.ID ' . $sort->direction));
			} else
				if ($sort->property == 'title')
				{
					$select = $select->order(array('s.title ' . $sort->direction));
				}
		} else
		{
			$select = $select->order(array('s.ID ASC'));
		}

		header('Content-type: application/json');
		echo $this->prepare_forms($select->limit($limit, $start)->query()->fetchAll());
		die();
	}

	public function get_forms_by_ids($ids = '')
	{
		global $wpdb, $kwfdb;

		$select = $kwfdb->select()->from(array('s' => $wpdb->prefix . 'kwf_forms'), array('*'))->joinLeft(array('ff' => $wpdb->prefix .
				'kwf_form_fields'), 's.ID = ff.form_ID', array('ff.arguments as field'))->where('s.ID IN (' . $ids . ')')->query();

		header('Content-type: application/json');
		echo $this->prepare_forms($select->fetchAll());
		die();
	}

	public function prepare_forms($rows)
	{
		$temp_row = array();

		foreach ($rows as $row)
		{
			$temp_field = json_decode($row['field']);

			if (!array_key_exists($row['ID'], $temp_row))
			{
				$temp_row[$row['ID']]['id'] = $row['ID'];
				$temp_row[$row['ID']]['title'] = $row['title'];
			}

			if ($temp_field != null)
			{
				$temp_row[$row['ID']]['fields'][] = $temp_field;
			}
		}

		$rows = array_values($temp_row);

		foreach ($rows as &$row)
		{
			$row['form'] = array();

			if (is_array($row['fields']))
			{
				foreach ($row['fields'] as &$field)
				{
					if (!array_key_exists($field->tab, $row['form']))
					{
						$row['form'][$field->tab] = array();
					}

					$row['form'][$field->tab][] = $field;

					unset($field->tab);
				}

				$row_items_temp = array();

				foreach ($row['form'] as $key => $value)
				{
					$row_items_temp[] = array(
						'title' => $key,
						'closable' => true,
						'layout' => 'form',
						'autoScroll' => true,
						'bodyPadding' => '10 30 10 10',
						'items' => $value);
				}

				$row['form'] = $row_items_temp;

				unset($row['fields']);
			}
		}

		$forms = new stdClass();
		$forms->forms = $rows;

		$forms_meta = $this->get_forms_meta();
		$forms->total = $forms_meta['totalresults'];

		return Zend_Json::prettyPrint(Zend_Json::encode($forms), array("indent" => "   "));
	}

	public function get_forms_meta()
	{
		global $wpdb, $kwfdb;

		$select_total = $kwfdb->select()->from(array('s' => $wpdb->prefix . 'kwf_forms'), array('COUNT(ID) as totalresults'));

		$select_total = $select_total->query();

		$total = $select_total->fetch();

		return $total;
	}

	public function update_forms()
	{
		$params = getParams();

		if (isset($params['forms']['id']) && !empty($params['forms']['id']))
		{
			$this->update_form($params['forms']);
			$form_ids = $params['forms']['id'];
		} else
		{
			foreach ($params['forms'] as $form_params)
			{
				$this->update_form($form_params);
				$form_id[] = $form_params['id'];
			}

			if (is_array($form_id))
				$form_ids = implode(',', $form_id);
		}

		$this->get_forms_by_ids($form_ids);
	}

	public function update_form($params)
	{
		global $wpdb, $kwfdb;

		$form_id = $params['id'];
		unset($params['id']);

		$fields = $params['form'];
		unset($params['form']);

		$params = array_addslashes($params);

		$kwfdb->update($wpdb->prefix . 'kwf_forms', $params, 'ID = ' . $form_id);
		$kwfdb->delete($wpdb->prefix . 'kwf_form_fields', 'form_ID = ' . $form_id);

		if (count($fields) > 0)
		{
			$insert_query = "INSERT INTO " . $wpdb->prefix . "kwf_form_fields (form_ID, arguments) VALUES ";
			$insert_values = array();

			foreach ($fields as $key => $field)
			{
				$insert_values[] = "(" . $form_id . ", '" . addslashes(Zend_Json::encode($field)) . "')";
			}

			$insert_query .= implode(',', $insert_values);

			$wpdb->query($insert_query);
		}
	}

	public function create_form()
	{
		global $wpdb, $kwfdb;

		$params = getParams();
		$params = $params['forms'];

		unset($params['id']);

		$fields = $params['form'];
		unset($params['form']);

		$params = array_addslashes($params);

		$kwfdb->insert($wpdb->prefix . 'kwf_forms', $params);
		$form_id = $kwfdb->lastInsertId();

		if (count($fields) > 0)
		{
			$insert_query = "INSERT INTO " . $wpdb->prefix . "kwf_form_fields (form_ID, arguments) VALUES ";
			$insert_values = array();

			foreach ($fields as $key => $field)
			{
				$insert_values[] = "(" . $form_id . ", '" . addslashes(Zend_Json::encode($field)) . "')";
			}

			$insert_query .= implode(',', $insert_values);

			$wpdb->query($insert_query);
		}

		$this->get_forms_by_ids($form_id);
	}

	public function delete_forms()
	{
		global $wpdb, $kwfdb;

		$params = getParams();
		$del_rows_arr = array();
		$del_rows = '';

		if (isset($params['forms']['id']) && !empty($params['forms']['id']))
		{
			$del_rows = $params['forms']['id'];
		} else
		{
			foreach ($params['forms'] as $key => $value)
			{
				$del_rows_arr[] = $value['id'];
			}
			$del_rows = implode(',', $del_rows_arr);
		}

		$kwfdb->delete($wpdb->prefix . 'kwf_forms', 'ID IN (' . $del_rows . ')');
	}

	public function add_forms_shortcodes()
	{
		add_shortcode('kwf-form', array($this, 'form_shortcode_content'));
	}

	public function form_shortcode_content($atts)
	{
		extract(shortcode_atts(array('id' => '0'), $atts));
		ob_start(); ?>
		<?php
		wp_nonce_field('kwf_6h2VQ876Q0O8G', 'kwf_noncename'); ?>
		<input type="hidden" id="form_ID" name="form_ID" value="<?php
		echo $atts['id'] ?>" />
		<input type="hidden" id="kwf_data" name="kwf_data" value="" />
		<div id="kahanit-wp-fw"></div>
		<?php
		$output = ob_get_contents();
		ob_end_clean();
		return $output;
	}
} ?>