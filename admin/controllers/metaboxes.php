<?php
class Kahanit_WP_FW_Metaboxes
{
	public function get_metaboxes()
	{
		global $wpdb, $kwfdb;

		$start = (isset($_GET['start']) && !empty($_GET['start'])) ? $_GET['start'] : 0;
		$limit = (isset($_GET['limit']) && !empty($_GET['limit'])) ? $_GET['limit'] : 10;
		$load = (isset($_GET['load']) && !empty($_GET['load'])) ? $_GET['load'] : 'false';
		$sort = (isset($_GET['sort']) && !empty($_GET['sort'])) ? json_decode(stripslashes($_GET['sort'])) : '';
		$sort = $sort[0];

		$select = $kwfdb->select()->from(array('s' => $wpdb->prefix . 'kwf_metaboxes'), array('*'));

		if (is_object($sort))
		{
			if ($sort->property == 'title')
			{
				$select = $select->order(array('s.title ' . $sort->direction));
			} else
				if ($sort->property == 'post_type')
				{
					$select = $select->order(array('s.post_type ' . $sort->direction));
				} else
					if ($sort->property == 'context')
					{
						$select = $select->order(array('s.context ' . $sort->direction));
					} else
						if ($sort->property == 'priority')
						{
							$select = $select->order(array('s.priority ' . $sort->direction));
						} else
							if ($sort->property == 'form_id')
							{
								$select = $select->order(array('s.form_id ' . $sort->direction));
							}

		} else
		{
			$select = $select->order(array('s.ID ASC'));
		}

		header('Content-type: application/json');
		echo $this->prepare_metaboxes($select->limit($limit, $start)->query()->fetchAll());
		die();
	}

	public function get_metaboxes_by_ids($ids = '')
	{
		global $wpdb, $kwfdb;

		$select = $kwfdb->select()->from(array('s' => $wpdb->prefix . 'kwf_metaboxes'), array('*'))->where('s.ID IN (' . $ids . ')')->
			query();

		header('Content-type: application/json');
		echo $this->prepare_metaboxes($select->fetchAll());
		die();
	}

	public function prepare_metaboxes($rows)
	{
		$metaboxes = new stdClass();

		foreach ($rows as &$row)
		{
			$row['id'] = $row['ID'];
			unset($row['ID']);
			
			$row['form_id'] = $row['form_ID'];
			unset($row['form_ID']);
		}

		$metaboxes->metaboxes = $rows;

		$metaboxes_meta = $this->get_metaboxes_meta();
		$metaboxes->total = $metaboxes_meta['totalresults'];

		return Zend_Json::prettyPrint(Zend_Json::encode($metaboxes), array("indent" => "   "));
	}

	public function get_metaboxes_meta()
	{
		global $wpdb, $kwfdb;

		$select_total = $kwfdb->select()->from(array('s' => $wpdb->prefix . 'kwf_metaboxes'), array('COUNT(ID) as totalresults'));

		$select_total = $select_total->query();

		$total = $select_total->fetch();

		return $total;
	}

	public function update_metaboxes()
	{
		$params = getParams();

		if (isset($params['metaboxes']['id']) && !empty($params['metaboxes']['id']))
		{
			$this->update_metabox($params['metaboxes']);
			$metabox_ids = $params['metaboxes']['id'];
		} else
		{
			foreach ($params['metaboxes'] as $metabox_params)
			{
				$this->update_metabox($metabox_params);
				$metabox_id[] = $metabox_params['id'];
			}

			if (is_array($metabox_id))
				$metabox_ids = implode(',', $metabox_id);
		}

		$this->get_metaboxes_by_ids($metabox_ids, 'updated');
	}

	public function update_metabox($params)
	{
		global $wpdb, $kwfdb;

		$metabox_id = $params['id'];
		unset($params['id']);

		$kwfdb->update($wpdb->prefix . 'kwf_metaboxes', $params, 'ID = ' . $metabox_id);
	}

	public function create_metabox()
	{
		global $wpdb, $kwfdb;

		$params = getParams();
		$params = $params['metaboxes'];

		unset($params['id']);

		$kwfdb->insert($wpdb->prefix . 'kwf_metaboxes', $params);

		$this->get_metaboxes_by_ids($kwfdb->lastInsertId(), 'inserted');
	}

	public function delete_metaboxes()
	{
		global $wpdb, $kwfdb;

		$params = getParams();
		$del_rows_arr = array();
		$del_rows = '';

		if (isset($params['metaboxes']['id']) && !empty($params['metaboxes']['id']))
		{
			$del_rows = $params['metaboxes']['id'];
		} else
		{
			foreach ($params['metaboxes'] as $key => $value)
			{
				$del_rows_arr[] = $value['id'];
			}
			$del_rows = implode(',', $del_rows_arr);
		}

		$kwfdb->delete($wpdb->prefix . 'kwf_metaboxes', 'ID IN (' . $del_rows . ')');
	}

	public function add_metaboxes()
	{
		global $wpdb, $kwfdb, $typenow;
		$print_script = false;

		$metaboxes = $kwfdb->select()->from(array('s' => $wpdb->prefix . 'kwf_metaboxes'), array('*'))->query()->fetchAll();

		if (is_array($metaboxes))
		{
			foreach ($metaboxes as $metabox)
			{
				if ($typenow == $metabox['post_type'])
				{
					$metabox = array_stripslashes($metabox);
					add_meta_box('kahanit-wp-fw-metabox-' . $metabox['ID'], $metabox['title'], array($this, 'metabox_content'), $metabox['post_type'],
						$metabox['context'], $metabox['priority'], array('id' => $metabox['ID']));

					$print_script = true;
				}
			}

			if ($print_script)
			{
				add_action('admin_print_scripts-post-new.php', array($this, "enqueue_scripts"), 10);
				add_action('admin_print_styles-post-new.php', array($this, "enqueue_styles"), 10);
				add_action('admin_print_scripts-post.php', array($this, "enqueue_scripts"), 10);
				add_action('admin_print_styles-post.php', array($this, "enqueue_styles"), 10);
			}
		}
	}

	public function metabox_content($post, $args)
	{
		global $wpdb, $kwfdb;

		$metaboxes = $kwfdb->select()->from(array('s' => $wpdb->prefix . 'kwf_metaboxes'), array(
			'before_form',
			'form_id',
			'after_form'))->where('ID = ' . $args['args']['id'])->query()->fetchAll();

		$content = $metaboxes[0]['before_form'] . '[kwf-form id="' . $metaboxes[0]['form_id'] . '"]' . $metaboxes[0]['after_form'];

		echo do_shortcode(stripslashes(str_replace('\n', '<br />', $content)));
	}

	public function enqueue_scripts()
	{
		$dev = false;

		wp_register_script('kahanit-wp-fw-ext-init', KAHANIT_WP_FW_JS_URL . "/metaboxes/ext-init.php", array("jquery-ui-sortable"));

		if ($dev)
		{
			wp_register_script('kahanit-wp-fw-ext', "http://cdn.sencha.io/ext-4.1.1-gpl/ext-debug.js", array("kahanit-wp-fw-ext-init"));
			wp_register_script('kahanit-wp-fw-options-pages', KAHANIT_WP_FW_JS_URL . "/options-pages/app.js", array("kahanit-wp-fw-ext"));
		} else
		{
			wp_register_script('kahanit-wp-fw-ext', KAHANIT_WP_FW_JS_URL . "/extjs/ext.js", array("kahanit-wp-fw-ext-init"));
			wp_register_script('kahanit-wp-fw-options-pages', KAHANIT_WP_FW_JS_URL . "/options-pages/app-all.js", array("kahanit-wp-fw-ext"));
		}

		wp_enqueue_script('kahanit-wp-fw-options-pages');
	}

	public function enqueue_styles()
	{
		wp_register_style('kahanit-wp-fw-ext-all-gray-scoped', KAHANIT_WP_FW_JS_URL . "/extjs/resources/css/ext-all-gray-scoped.css");

		wp_enqueue_style('kahanit-wp-fw-ext-all-gray-scoped');
	}

	public function get_form_options()
	{
		if (!wp_verify_nonce($_POST['kwf_noncename'], 'kwf_6h2VQ876Q0O8G'))
			return;

		global $wpdb, $kwfdb;

		$form_ID = (int)$_POST['form_ID'];

		$form_fields = $kwfdb->select()->from($wpdb->prefix . 'kwf_form_fields')->where('form_ID = ' . $form_ID)->query()->fetchAll();

		$tabs = array();

		foreach ($form_fields as $form_field)
		{
			$tab_item = json_decode($form_field['arguments']);

			if (!array_key_exists($tab_item->tab, $tabs))
			{
				$tabs[$tab_item->tab] = array();
			}

			$this->set_field_value($tab_item);

			$tabs[$tab_item->tab][] = $tab_item;
		}

		$json = array();

		foreach ($tabs as $key => $value)
		{
			$json[] = array('title' => $key, 'items' => $value);
		}

		header('Content-type: application/json');
		echo Zend_Json::prettyPrint(Zend_Json::encode($json), array("indent" => "   "));
		die();
	}

	public function set_field_value($field)
	{
		$post_ID = (int)$_POST['post_ID'];

		switch ($field->xtype)
		{
			case 'textfield':
			case 'textareafield':
			case 'combobox':
				$value = get_post_meta($post_ID, 'kwf_' . str_replace("[]", "", $field->name), true);

				if ($value != '')
				{
					$field->value = $value;
				}
				break;
			case 'checkboxgroup':
			case 'radiogroup':
				$field_name = $field->items[0]->name;

				$value = get_post_meta($post_ID, 'kwf_' . str_replace("[]", "", $field_name), true);

				if ($value != '')
				{
					$field->value->$field_name = $value;
				}
				break;
			case 'repetitivefield':
				foreach ($field->items as $item)
				{
					$field_name = $item->name;

					$value = get_post_meta($post_ID, 'kwf_' . str_replace("[]", "", $field_name), true);

					if ($value != '')
					{
						$field->value->$field_name = $value;
					}
				}
				break;
		}
	}

	public function save_form_options($postid = '')
	{
		if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE)
			return;

		if (!wp_verify_nonce($_POST['kwf_noncename'], 'kwf_6h2VQ876Q0O8G'))
			return;

		if (isset($_POST['post_ID']) && isset($_POST['kwf_data']))
		{
			$post_ID = (int)$_POST['post_ID'];
			$post_data = json_decode(stripslashes($_POST['kwf_data']), true);

			foreach ($post_data as $key => $value)
			{
				update_post_meta($post_ID, 'kwf_' . $key, $value);
			}

			$json = new stdClass();
			$json->success = true;
			$json->message = "options saved successfully.";

			$json->data = new stdClass();

			foreach ($post_data as $key => $value)
			{
				$json->data->$key = get_post_meta($post_ID, 'kwf_' . $key, true);
			}

			if ($postid == '')
			{
				header('Content-type: application/json');
				echo Zend_Json::prettyPrint(Zend_Json::encode($json), array("indent" => "   "));
				die();
			}
		}
	}
} ?>