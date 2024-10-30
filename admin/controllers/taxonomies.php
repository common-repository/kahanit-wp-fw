<?php
class Kahanit_WP_FW_Taxonomies
{
	public $labels = array(
		'name',
		'singular_name',
		'search_items',
		'popular_items',
		'all_items',
		'parent_item',
		'parent_item_colon',
		'edit_item',
		'update_item',
		'add_new_item',
		'new_item_name',
		'separate_items_with_commas',
		'add_or_remove_items',
		'choose_from_most_used',
		'menu_name');

	public $others = array(
		'public',
		'show_in_nav_menus',
		'show_ui',
		'show_tagcloud',
		'hierarchical');

	public function get_taxonomies()
	{
		global $wpdb, $kwfdb;

		$start = (isset($_GET['start']) && !empty($_GET['start'])) ? $_GET['start'] : 0;
		$limit = (isset($_GET['limit']) && !empty($_GET['limit'])) ? $_GET['limit'] : 10;
		$load = (isset($_GET['load']) && !empty($_GET['load'])) ? $_GET['load'] : 'false';
		$sort = (isset($_GET['sort']) && !empty($_GET['sort'])) ? json_decode(stripslashes($_GET['sort'])) : '';
		$sort = $sort[0];

		$select = $kwfdb->select()->from(array('t' => $wpdb->prefix . 'kwf_taxonomies'), array('*'));

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
					if ($sort->property == 'taxonomy')
					{
						$select = $select->order(array('t.taxonomy ' . $sort->direction));
					}
		} else
		{
			$select = $select->order(array('t.ID ASC'));
		}

		header('Content-type: application/json');
		echo $this->prepare_taxonomies($select->limit($limit, $start)->query()->fetchAll());
		die();
	}

	public function get_taxonomies_by_ids($ids = '')
	{
		global $wpdb, $kwfdb;

		$select = $kwfdb->select()->from(array('t' => $wpdb->prefix . 'kwf_taxonomies'), array('*'))->where('t.ID IN (' . $ids . ')')->
			query();

		header('Content-type: application/json');
		echo $this->prepare_taxonomies($select->fetchAll());
		die();
	}

	public function prepare_taxonomies($rows)
	{
		$taxonomies = new stdClass();
		$taxonomy = array();

		foreach ($rows as $row)
		{
			$row_arguments = json_decode(stripslashes($row['arguments']), true);

			$taxonomy['id'] = $row['ID'];
			$taxonomy['taxonomy'] = $row['taxonomy'];
			$taxonomy['description'] = $row['description'];
			$taxonomy['post_types'] = json_decode($row['post_types']);

			foreach ($this->labels as $label)
				$taxonomy['labels_' . $label] = isset($row_arguments['labels'][$label]) ? $row_arguments['labels'][$label] : '';

			$taxonomy['labels_name'] = $row['name'];

			foreach ($this->others as $other)
				$taxonomy[$other] = $row_arguments[$other];

			$taxonomies->taxonomies[] = $taxonomy;
		}

		$taxonomies_meta = $this->get_taxonomies_meta();
		$taxonomies->total = $taxonomies_meta['totalresults'];

		return Zend_Json::prettyPrint(Zend_Json::encode($taxonomies), array("indent" => "   "));
	}

	public function prepare_incoming_taxonomies($params)
	{
		$temp_params = array();
		$labels = array();
		$temp_key = '';

		if (is_array($params))
		{
			unset($params['id']);
			$pattern_labels = '/^labels_/';

			foreach ($params as $key => $value)
			{
				if (preg_match($pattern_labels, $key))
				{
					$temp_key = preg_replace($pattern_labels, '', $key);
					$labels[$temp_key] = $value;
					unset($params[$key]);
				}
			}
		}

		$temp_params['taxonomy'] = $params['taxonomy'];
		unset($params['taxonomy']);

		$temp_params['name'] = $labels['name'];
		unset($labels['name']);

		$temp_params['post_types'] = json_encode($params['post_types']);
		unset($params['post_types']);

		$temp_params['description'] = $params['description'];
		unset($params['description']);

		$params['labels'] = $labels;

		$temp_params['arguments'] = json_encode($params);

		return $temp_params;
	}

	public function get_taxonomies_meta()
	{
		global $wpdb, $kwfdb;

		$select_total = $kwfdb->select()->from(array('t' => $wpdb->prefix . 'kwf_taxonomies'), array('COUNT(taxonomy) as totalresults'));

		$select_total = $select_total->query();

		$total = $select_total->fetch();

		return $total;
	}

	public function update_taxonomies()
	{
		$params = getParams();

		if (isset($params['taxonomies']['id']) && !empty($params['taxonomies']['id']))
		{
			$this->update_taxonomy($params['taxonomies']);
			$taxonomy_ids = $params['taxonomies']['id'];
		} else
		{
			foreach ($params['taxonomies'] as $taxonomy_params)
			{
				$this->update_taxonomy($taxonomy_params);
				$taxonomy_id[] = $taxonomy_params['id'];
			}

			if (is_array($taxonomy_id))
				$taxonomy_ids = implode(',', $taxonomy_id);
		}

		$this->get_taxonomies_by_ids($taxonomy_ids, 'updated');
	}

	public function update_taxonomy($params)
	{
		global $wpdb, $kwfdb;

		$taxonomy_id = $params['id'];
		$params = $this->prepare_incoming_taxonomies($params);

		$kwfdb->update($wpdb->prefix . 'kwf_taxonomies', $params, 'ID = ' . $taxonomy_id);

		// Update Post Types
		$this->update_post_types($params);
	}

	public function update_post_types($params)
	{
		global $wpdb, $kwfdb;

		$post_types = json_decode($params['post_types']);

		$select_post_types = $kwfdb->select()->from(array('t' => $wpdb->prefix . 'kwf_types'), array('taxonomies', 'post_type'))->where('(t.post_type IN ("' .
			implode('","', $post_types) . '") AND t.taxonomies NOT LIKE "%\"' . $params['taxonomy'] . '\"%") OR t.taxonomies LIKE "%\"' . $params['taxonomy'] .
			'\"%"')->query()->fetchAll();

		foreach ($select_post_types as $select_post_type)
		{
			$post_type_taxonomies = json_decode($select_post_type['taxonomies']);

			if (in_array($select_post_type['post_type'], $post_types))
			{
				if (!in_array($params['taxonomy'], $post_type_taxonomies))
					$post_type_taxonomies[] = $params['taxonomy'];
			} else
			{
				unset($post_type_taxonomies[array_search($params['taxonomy'], $post_type_taxonomies)]);
			}

			$post_type_taxonomies = array_values($post_type_taxonomies);

			$kwfdb->update($wpdb->prefix . 'kwf_types', array('taxonomies' => json_encode($post_type_taxonomies)), 'post_type = "' . $select_post_type['post_type'] .
				'"');
		}
	}

	public function create_taxonomy()
	{
		global $wpdb, $kwfdb;

		$params = getParams();
		$params = $this->prepare_incoming_taxonomies($params['taxonomies']);

		$kwfdb->insert($wpdb->prefix . 'kwf_taxonomies', $params);
		$last_insert_id = $kwfdb->lastInsertId();

		// Update Post Types
		$this->update_post_types($params);

		$this->get_taxonomies_by_ids($last_insert_id, 'inserted');
	}

	public function delete_taxonomies()
	{
		global $wpdb, $kwfdb;

		$params = getParams();
		$del_rows_arr = array();
		$del_rows = '';

		if (isset($params['taxonomies']['id']) && !empty($params['taxonomies']['id']))
		{
			$del_rows = $params['taxonomies']['id'];
		} else
		{
			foreach ($params['taxonomies'] as $key => $value)
			{
				$del_rows_arr[] = $value['id'];
			}
			$del_rows = implode(',', $del_rows_arr);
		}

		$kwfdb->delete($wpdb->prefix . 'kwf_taxonomies', 'ID IN (' . $del_rows . ')');
	}

	public function get_post_types()
	{
		$taxonomies = get_post_types('', 'objects', 'or');

		$output_arr = array();
		$arr_counter = 0;
		foreach ($taxonomies as $taxonomy)
		{
			if ($taxonomy->name != 'attachment' && $taxonomy->name != 'revision' && $taxonomy->name != 'nav_menu_item')
			{
				$output_arr[$arr_counter]['post_type'] = $taxonomy->name;
				$output_arr[$arr_counter]['labels_name'] = $taxonomy->labels->name;
				$arr_counter++;
			}
		}

		header('Content-type: application/json');
		echo json_encode($output_arr);
		die();
	}

	public function register_taxonomies()
	{
		global $wpdb, $kwfdb;

		$taxonomies = $kwfdb->select()->from(array('t' => $wpdb->prefix . 'kwf_taxonomies'), array('*'))->query()->fetchAll();

		if (is_array($taxonomies))
		{
			foreach ($taxonomies as $taxonomy)
			{
				$taxonomy['arguments'] = json_decode($taxonomy['arguments'], true);
				$taxonomy['arguments']['labels']['name'] = $taxonomy['name'];

				foreach ($taxonomy['arguments']['labels'] as $key => $value)
				{
					$taxonomy['arguments']['labels'][$key] = str_replace("{plural}", $taxonomy['arguments']['labels']['name'], $value);
					$taxonomy['arguments']['labels'][$key] = str_replace("{singular}", $taxonomy['arguments']['labels']['singular_name'], $taxonomy['arguments']['labels'][$key]);
				}

				register_taxonomy($taxonomy['taxonomy'], json_decode($taxonomy['post_types']), $taxonomy['arguments']);

				if ($taxonomy['is_url_flushed'] == 0)
				{
					flush_rewrite_rules();
					$kwfdb->update($wpdb->prefix . 'kwf_taxonomies', array('is_url_flushed' => 1), 'ID = ' . $taxonomy['ID']);
				}
			}
		}
	}
} ?>