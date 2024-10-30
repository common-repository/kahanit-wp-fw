<?php
class Kahanit_WP_FW_Options_Pages
{
	public function get_options_pages()
	{
		global $wpdb, $kwfdb;

		$start = (isset($_GET['start']) && !empty($_GET['start'])) ? $_GET['start'] : 0;
		$limit = (isset($_GET['limit']) && !empty($_GET['limit'])) ? $_GET['limit'] : 10;
		$load = (isset($_GET['load']) && !empty($_GET['load'])) ? $_GET['load'] : 'false';
		$sort = (isset($_GET['sort']) && !empty($_GET['sort'])) ? json_decode(stripslashes($_GET['sort'])) : '';
		$sort = $sort[0];

		$select = $kwfdb->select()->from(array('s' => $wpdb->prefix . 'kwf_options_pages'), array('*'));

		if (is_object($sort))
		{
			if ($sort->property == 'menu_title')
			{
				$select = $select->order(array('s.menu_title ' . $sort->direction));
			} else
				if ($sort->property == 'page_title')
				{
					$select = $select->order(array('s.page_title ' . $sort->direction));
				} else
					if ($sort->property == 'parent_slug')
					{
						$select = $select->order(array('s.parent_slug ' . $sort->direction));
					} else
						if ($sort->property == 'capability')
						{
							$select = $select->order(array('s.capability ' . $sort->direction));
						} else
							if ($sort->property == 'menu_slug')
							{
								$select = $select->order(array('s.menu_slug ' . $sort->direction));
							} else
								if ($sort->property == 'position')
								{
									$select = $select->order(array('s.position ' . $sort->direction));
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
		echo $this->prepare_options_pages($select->limit($limit, $start)->query()->fetchAll());
		die();
	}

	public function get_options_pages_by_ids($ids = '')
	{
		global $wpdb, $kwfdb;

		$select = $kwfdb->select()->from(array('s' => $wpdb->prefix . 'kwf_options_pages'), array('*'))->where('s.ID IN (' . $ids . ')')->
			query();

		header('Content-type: application/json');
		echo $this->prepare_options_pages($select->fetchAll());
		die();
	}

	public function prepare_options_pages($rows)
	{
		$options_pages = new stdClass();

		foreach ($rows as &$row)
		{
			$row['id'] = $row['ID'];
			unset($row['ID']);
			
			$row['form_id'] = $row['form_ID'];
			unset($row['form_ID']);
		}

		$options_pages->options_pages = $rows;

		$options_pages_meta = $this->get_options_pages_meta();
		$options_pages->total = $options_pages_meta['totalresults'];

		return Zend_Json::prettyPrint(Zend_Json::encode($options_pages), array("indent" => "   "));
	}

	public function get_options_pages_meta()
	{
		global $wpdb, $kwfdb;

		$select_total = $kwfdb->select()->from(array('s' => $wpdb->prefix . 'kwf_options_pages'), array('COUNT(ID) as totalresults'));

		$select_total = $select_total->query();

		$total = $select_total->fetch();

		return $total;
	}

	public function update_options_pages()
	{
		$params = getParams();

		if (isset($params['options_pages']['id']) && !empty($params['options_pages']['id']))
		{
			$this->update_options_page($params['options_pages']);
			$options_page_ids = $params['options_pages']['id'];
		} else
		{
			foreach ($params['options_pages'] as $options_page_params)
			{
				$this->update_options_page($options_page_params);
				$options_page_id[] = $options_page_params['id'];
			}

			if (is_array($options_page_id))
				$options_page_ids = implode(',', $options_page_id);
		}

		$this->get_options_pages_by_ids($options_page_ids, 'updated');
	}

	public function update_options_page($params)
	{
		global $wpdb, $kwfdb;

		$options_page_id = $params['id'];
		unset($params['id']);

		$kwfdb->update($wpdb->prefix . 'kwf_options_pages', $params, 'ID = ' . $options_page_id);
	}

	public function create_options_page()
	{
		global $wpdb, $kwfdb;

		$params = getParams();
		$params = $params['options_pages'];

		unset($params['id']);

		$kwfdb->insert($wpdb->prefix . 'kwf_options_pages', $params);

		$this->get_options_pages_by_ids($kwfdb->lastInsertId(), 'inserted');
	}

	public function delete_options_pages()
	{
		global $wpdb, $kwfdb;

		$params = getParams();
		$del_rows_arr = array();
		$del_rows = '';

		if (isset($params['options_pages']['id']) && !empty($params['options_pages']['id']))
		{
			$del_rows = $params['options_pages']['id'];
		} else
		{
			foreach ($params['options_pages'] as $key => $value)
			{
				$del_rows_arr[] = $value['id'];
			}
			$del_rows = implode(',', $del_rows_arr);
		}

		$kwfdb->delete($wpdb->prefix . 'kwf_options_pages', 'ID IN (' . $del_rows . ')');
	}

	public function add_options_pages()
	{
		global $wpdb, $kwfdb;

		$options_pages = $kwfdb->select()->from(array('s' => $wpdb->prefix . 'kwf_options_pages'), array('*'))->query()->fetchAll();

		if (is_array($options_pages))
		{
			foreach ($options_pages as $options_page)
			{
				unset($options_page['ID']);
				$options_page = array_stripslashes($options_page);

				if (isset($options_page['parent_slug']) && !empty($options_page['parent_slug']))
					$options_page = add_submenu_page($options_page['parent_slug'], $options_page['page_title'], $options_page['menu_title'], $options_page['capability'],
						$options_page['menu_slug'], array($this, 'options_page_content'));
				else
					$options_page = add_menu_page($options_page['page_title'], $options_page['menu_title'], $options_page['capability'], $options_page['menu_slug'],
						array($this, 'options_page_content'), $options_page['icon_url'], $options_page['position']);

				add_action('admin_print_scripts-' . $options_page, array($this, "enqueue_scripts"));
				add_action('admin_print_styles-' . $options_page, array($this, "enqueue_styles"));
			}
		}
	}

	public function options_page_content()
	{
		global $wpdb, $kwfdb;

		$options_pages = $kwfdb->select()->from(array('s' => $wpdb->prefix . 'kwf_options_pages'), array(
			'before_form',
			'form_id',
			'after_form',
			'page_heading'))->where('menu_slug = "' . $_GET['page'] . '"')->query()->fetchAll();

		$content = $options_pages[0]['before_form'] . '[kwf-form id="' . $options_pages[0]['form_id'] . '"]' . $options_pages[0]['after_form']; ?>
		<div class="wrap">
			<h2>
        <?php
		echo stripslashes($options_pages[0]['page_heading']); ?></h2>
		<?php
		echo do_shortcode(stripslashes(str_replace('\n', '<br />', $content))); ?>
			<br class="clear" />
		</div>
        <?php
	}

	public function enqueue_scripts()
	{
		$dev = false;

		wp_register_script('kahanit-wp-fw-ext-init', KAHANIT_WP_FW_JS_URL . "/options-pages/ext-init.php", array("jquery-ui-sortable"));

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
		switch ($field->xtype)
		{
			case 'textfield':
			case 'textareafield':
			case 'combobox':
				$value = get_option('kwf_' . str_replace("[]", "", $field->name), 'kwf_option_not_set');

				if ($value != 'kwf_option_not_set')
				{
					$field->value = $value;
				}
				break;
			case 'checkboxgroup':
			case 'radiogroup':
				$field_name = $field->items[0]->name;

				$value = get_option('kwf_' . str_replace("[]", "", $field_name), 'kwf_option_not_set');

				if ($value != 'kwf_option_not_set')
				{
					$field->value->$field_name = $value;
				}
				break;
			case 'repetitivefield':
				foreach ($field->items as $item)
				{
					$field_name = $item->name;

					$value = get_option('kwf_' . str_replace("[]", "", $field_name), 'kwf_option_not_set');

					if ($value != 'kwf_option_not_set')
					{
						$field->value->$field_name = $value;
					}
				}
				break;
		}
	}

	public function save_form_options()
	{
		if (!wp_verify_nonce($_POST['kwf_noncename'], 'kwf_6h2VQ876Q0O8G'))
			return;

		$post_data = json_decode(stripslashes($_POST['kwf_data']), true);

		foreach ($post_data as $key => $value)
		{
			update_option('kwf_' . $key, $value);
		}

		$json = new stdClass();
		$json->success = true;
		$json->message = "options saved successfully.";

		$json->data = new stdClass();

		foreach ($post_data as $key => $value)
		{
			$json->data->$key = get_option('kwf_' . $key);
		}

		header('Content-type: application/json');
		echo Zend_Json::prettyPrint(Zend_Json::encode($json), array("indent" => "   "));
		die();
	}
} ?>