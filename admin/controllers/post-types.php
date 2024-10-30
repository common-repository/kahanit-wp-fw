<?php
class Kahanit_WP_FW_Post_Types
{
	public $supports = array(
		'title',
		'editor',
		'author',
		'thumbnail',
		'excerpt',
		'trackbacks',
		'custom-fields',
		'comments',
		'revisions',
		'page-attributes',
		'post-formats');

	public $labels = array(
		'name',
		'singular_name',
		'add_new',
		'all_items',
		'add_new_item',
		'edit_item',
		'new_item',
		'view_item',
		'search_items',
		'not_found',
		'not_found_in_trash',
		'parent_item_colon',
		'menu_name');

	public $others = array(
		'public',
		'exclude_from_search',
		'publicly_queryable',
		'show_ui',
		'show_in_nav_menus',
		'show_in_menu',
		'show_in_admin_bar',
		'menu_position',
		'menu_icon',
		'hierarchical',
		'has_archive',
		'permalink_epmask',
		'can_export');

	public function get_post_types()
	{
		global $wpdb, $kwfdb;

		$start = (isset($_GET['start']) && !empty($_GET['start'])) ? $_GET['start'] : 0;
		$limit = (isset($_GET['limit']) && !empty($_GET['limit'])) ? $_GET['limit'] : 10;
		$load = (isset($_GET['load']) && !empty($_GET['load'])) ? $_GET['load'] : 'false';
		$sort = (isset($_GET['sort']) && !empty($_GET['sort'])) ? json_decode(stripslashes($_GET['sort'])) : '';
		$sort = $sort[0];

		$select = $kwfdb->select()->from(array('t' => $wpdb->prefix . 'kwf_types'), array('*'));

		if (is_object($sort))
		{
			if ($sort->property == 'labels_name')
			{
				$select = $select->order(array('t.name ' . $sort->direction));
			} else
				if ($sort->property == 'description')
				{
					$select = $select->order(array('t.description ' . $sort->direction));
				} else
					if ($sort->property == 'post_type')
					{
						$select = $select->order(array('t.post_type ' . $sort->direction));
					}
		} else
		{
			$select = $select->order(array('t.ID ASC'));
		}

		header('Content-type: application/json');
		echo $this->prepare_post_types($select->limit($limit, $start)->query()->fetchAll());
		die();
	}

	public function get_post_types_by_ids($ids = '')
	{
		global $wpdb, $kwfdb;

		$select = $kwfdb->select()->from(array('t' => $wpdb->prefix . 'kwf_types'), array('*'))->where('t.ID IN (' . $ids . ')')->query();

		header('Content-type: application/json');
		echo $this->prepare_post_types($select->fetchAll());
		die();
	}

	public function prepare_post_types($rows)
	{
		$post_types = new stdClass();
		$post_type = array();

		foreach ($rows as $row)
		{
			$row_arguments = json_decode(stripslashes($row['arguments']), true);

			$post_type['id'] = $row['ID'];
			$post_type['post_type'] = $row['post_type'];
			$post_type['description'] = $row['description'];
			$post_type['taxonomies'] = json_decode($row['taxonomies']);

			foreach ($this->supports as $support)
			{
				if (in_array($support, $row_arguments['supports']))
					$post_type['supports_' . $support] = "1";
				else
					$post_type['supports_' . $support] = "0";
			}

			foreach ($this->labels as $label)
				$post_type['labels_' . $label] = isset($row_arguments['labels'][$label]) ? $row_arguments['labels'][$label] : '';

			$post_type['labels_name'] = $row['name'];

			foreach ($this->others as $other)
				$post_type[$other] = $row_arguments[$other];

			$post_types->post_types[] = $post_type;
		}

		$post_types_meta = $this->get_post_types_meta();
		$post_types->total = $post_types_meta['totalresults'];

		return Zend_Json::prettyPrint(Zend_Json::encode($post_types), array("indent" => "   "));
	}

	public function prepare_incoming_post_types($params)
	{
		$temp_params = array();
		$supports = array();
		$labels = array();
		$temp_key = '';

		if (is_array($params))
		{
			unset($params['id']);
			$pattern_supports = '/^supports_/';
			$pattern_labels = '/^labels_/';

			foreach ($params as $key => $value)
			{
				if (preg_match($pattern_supports, $key))
				{
					$temp_key = preg_replace($pattern_supports, '', $key);
					if ($value)
						$supports[] = $temp_key;
					unset($params[$key]);
				} else
					if (preg_match($pattern_labels, $key))
					{
						$temp_key = preg_replace($pattern_labels, '', $key);
						$labels[$temp_key] = $value;
						unset($params[$key]);
					}
			}
		}

		$temp_params['post_type'] = $params['post_type'];
		unset($params['post_type']);

		$temp_params['name'] = $labels['name'];
		unset($labels['name']);

		$temp_params['taxonomies'] = json_encode($params['taxonomies']);
		unset($params['taxonomies']);

		$temp_params['description'] = $params['description'];
		unset($params['description']);

		if ($params['menu_icon'] == '')
			unset($params['menu_icon']);

		$params['supports'] = $supports;

		$params['labels'] = $labels;

		$temp_params['arguments'] = json_encode($params);

		return $temp_params;
	}

	public function get_post_types_meta()
	{
		global $wpdb, $kwfdb;

		$select_total = $kwfdb->select()->from(array('t' => $wpdb->prefix . 'kwf_types'), array('COUNT(post_type) as totalresults'));

		$select_total = $select_total->query();

		$total = $select_total->fetch();

		return $total;
	}

	public function update_post_types()
	{
		$params = getParams();

		if (isset($params['post_types']['id']) && !empty($params['post_types']['id']))
		{
			$this->update_post_type($params['post_types']);
			$post_type_ids = $params['post_types']['id'];
		} else
		{
			foreach ($params['post_types'] as $post_type_params)
			{
				$this->update_post_type($post_type_params);
				$post_type_id[] = $post_type_params['id'];
			}

			if (is_array($post_type_id))
				$post_type_ids = implode(',', $post_type_id);
		}

		$this->get_post_types_by_ids($post_type_ids, 'updated');
	}

	public function update_post_type($params)
	{
		global $wpdb, $kwfdb;

		$post_type_id = $params['id'];
		$params = $this->prepare_incoming_post_types($params);

		$kwfdb->update($wpdb->prefix . 'kwf_types', $params, 'ID = ' . $post_type_id);

		// Update Taxonomies
		$this->update_taxonomies($params);
	}

	public function update_taxonomies($params)
	{
		global $wpdb, $kwfdb;

		$taxonomies = json_decode($params['taxonomies']);

		$select_taxonomies = $kwfdb->select()->from(array('t' => $wpdb->prefix . 'kwf_taxonomies'), array('post_types', 'taxonomy'))->
			where('(t.taxonomy IN ("' . implode('","', $taxonomies) . '") AND t.post_types NOT LIKE "%\"' . $params['post_type'] . '\"%") OR t.post_types LIKE "%\"' .
			$params['post_type'] . '\"%"')->query()->fetchAll();

		foreach ($select_taxonomies as $select_taxonomy)
		{
			$taxonomy_post_types = json_decode($select_taxonomy['post_types']);

			if (in_array($select_taxonomy['taxonomy'], $taxonomies))
			{
				if (!in_array($params['post_type'], $taxonomy_post_types))
					$taxonomy_post_types[] = $params['post_type'];
			} else
			{
				unset($taxonomy_post_types[array_search($params['post_type'], $taxonomy_post_types)]);
			}

			$taxonomy_post_types = array_values($taxonomy_post_types);

			$kwfdb->update($wpdb->prefix . 'kwf_taxonomies', array('post_types' => json_encode($taxonomy_post_types)), 'taxonomy = "' . $select_taxonomy['taxonomy'] .
				'"');
		}
	}

	public function create_post_type()
	{
		global $wpdb, $kwfdb;

		$params = getParams();
		$params = $this->prepare_incoming_post_types($params['post_types']);

		$kwfdb->insert($wpdb->prefix . 'kwf_types', $params);
		$last_insert_id = $kwfdb->lastInsertId();

		// Update Taxonomies
		$this->update_taxonomies($params);

		$this->get_post_types_by_ids($last_insert_id, 'inserted');
	}

	public function delete_post_types()
	{
		global $wpdb, $kwfdb;

		$params = getParams();
		$del_rows_arr = array();
		$del_rows = '';

		if (isset($params['post_types']['id']) && !empty($params['post_types']['id']))
		{
			$del_rows = $params['post_types']['id'];
		} else
		{
			foreach ($params['post_types'] as $key => $value)
			{
				$del_rows_arr[] = $value['id'];
			}
			$del_rows = implode(',', $del_rows_arr);
		}

		$kwfdb->delete($wpdb->prefix . 'kwf_types', 'ID IN (' . $del_rows . ')');
	}

	public function get_taxonomies()
	{
		$taxonomies = get_taxonomies('', 'objects', 'or');

		$output_arr = array();
		$arr_counter = 0;
		foreach ($taxonomies as $taxonomy)
		{
			if ($taxonomy->name != 'nav_menu' && $taxonomy->name != 'link_category' && $taxonomy->name != 'post_format')
			{
				$output_arr[$arr_counter]['taxonomy'] = $taxonomy->name;
				$output_arr[$arr_counter]['labels_name'] = $taxonomy->labels->name;
				$arr_counter++;
			}
		}

		header('Content-type: application/json');
		echo json_encode($output_arr);
		die();
	}

	public function register_post_types()
	{
		global $wpdb, $kwfdb;

		$post_types = $kwfdb->select()->from(array('t' => $wpdb->prefix . 'kwf_types'), array('*'))->query()->fetchAll();

		if (is_array($post_types))
		{
			foreach ($post_types as $post_type)
			{
				$post_type['arguments'] = json_decode($post_type['arguments'], true);
				$post_type['arguments']['description'] = $post_type['description'];
				$post_type['arguments']['taxonomies'] = json_decode($post_type['taxonomies'], true);
				$post_type['arguments']['labels']['name'] = $post_type['name'];

				foreach ($post_type['arguments']['labels'] as $key => $value)
				{
					$post_type['arguments']['labels'][$key] = str_replace("{plural}", $post_type['arguments']['labels']['name'], $value);
					$post_type['arguments']['labels'][$key] = str_replace("{singular}", $post_type['arguments']['labels']['singular_name'], $post_type['arguments']['labels'][$key]);
				}

				register_post_type($post_type['post_type'], $post_type['arguments']);

				if ($post_type['is_url_flushed'] == 0)
				{
					flush_rewrite_rules();
					$kwfdb->update($wpdb->prefix . 'kwf_types', array('is_url_flushed' => 1), 'ID = ' . $post_type['ID']);
				}
			}
		}
	}
} ?>