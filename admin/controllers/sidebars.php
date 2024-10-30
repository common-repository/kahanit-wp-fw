<?php
class Kahanit_WP_FW_Sidebars
{
	public function get_sidebars()
	{
		global $wpdb, $kwfdb;

		$start = (isset($_GET['start']) && !empty($_GET['start'])) ? $_GET['start'] : 0;
		$limit = (isset($_GET['limit']) && !empty($_GET['limit'])) ? $_GET['limit'] : 10;
		$load = (isset($_GET['load']) && !empty($_GET['load'])) ? $_GET['load'] : 'false';
		$sort = (isset($_GET['sort']) && !empty($_GET['sort'])) ? json_decode(stripslashes($_GET['sort'])) : '';
		$sort = $sort[0];

		$select = $kwfdb->select()->from(array('s' => $wpdb->prefix . 'kwf_sidebars'), array('*'));

		if (is_object($sort))
		{
			if ($sort->property == 'name')
			{
				$select = $select->order(array('s.name ' . $sort->direction));
			} else
				if ($sort->property == 'description')
				{
					$select = $select->order(array('s.description ' . $sort->direction));
				}
		} else
		{
			$select = $select->order(array('s.ID ASC'));
		}

		header('Content-type: application/json');
		echo $this->prepare_sidebars($select->limit($limit, $start)->query()->fetchAll());
		die();
	}

	public function get_sidebars_by_ids($ids = '')
	{
		global $wpdb, $kwfdb;

		$select = $kwfdb->select()->from(array('s' => $wpdb->prefix . 'kwf_sidebars'), array('*'))->where('s.ID IN (' . $ids . ')')->
			query();

		header('Content-type: application/json');
		echo $this->prepare_sidebars($select->fetchAll());
		die();
	}

	public function prepare_sidebars($rows)
	{
		$sidebars = new stdClass();

		foreach ($rows as &$row)
		{
			$row['id'] = $row['ID'];
			unset($row['ID']);
		}

		$sidebars->sidebars = $rows;

		$sidebars_meta = $this->get_sidebars_meta();
		$sidebars->total = $sidebars_meta['totalresults'];

		return Zend_Json::prettyPrint(Zend_Json::encode($sidebars), array("indent" => "   "));
	}

	public function get_sidebars_meta()
	{
		global $wpdb, $kwfdb;

		$select_total = $kwfdb->select()->from(array('s' => $wpdb->prefix . 'kwf_sidebars'), array('COUNT(ID) as totalresults'));

		$select_total = $select_total->query();

		$total = $select_total->fetch();

		return $total;
	}

	public function update_sidebars()
	{
		$params = getParams();

		if (isset($params['sidebars']['id']) && !empty($params['sidebars']['id']))
		{
			$this->update_sidebar($params['sidebars']);
			$sidebar_ids = $params['sidebars']['id'];
		} else
		{
			foreach ($params['sidebars'] as $sidebar_params)
			{
				$this->update_sidebar($sidebar_params);
				$sidebar_id[] = $sidebar_params['id'];
			}

			if (is_array($sidebar_id))
				$sidebar_ids = implode(',', $sidebar_id);
		}

		$this->get_sidebars_by_ids($sidebar_ids, 'updated');
	}

	public function update_sidebar($params)
	{
		global $wpdb, $kwfdb;

		$sidebar_id = $params['id'];
		unset($params['id']);

		$kwfdb->update($wpdb->prefix . 'kwf_sidebars', $params, 'ID = ' . $sidebar_id);
	}

	public function create_sidebar()
	{
		global $wpdb, $kwfdb;

		$params = getParams();
		$params = $params['sidebars'];

		unset($params['id']);

		$kwfdb->insert($wpdb->prefix . 'kwf_sidebars', $params);

		$this->get_sidebars_by_ids($kwfdb->lastInsertId(), 'inserted');
	}

	public function delete_sidebars()
	{
		global $wpdb, $kwfdb;

		$params = getParams();
		$del_rows_arr = array();
		$del_rows = '';

		if (isset($params['sidebars']['id']) && !empty($params['sidebars']['id']))
		{
			$del_rows = $params['sidebars']['id'];
		} else
		{
			foreach ($params['sidebars'] as $key => $value)
			{
				$del_rows_arr[] = $value['id'];
			}
			$del_rows = implode(',', $del_rows_arr);
		}

		$kwfdb->delete($wpdb->prefix . 'kwf_sidebars', 'ID IN (' . $del_rows . ')');
	}

	public function register_sidebars()
	{
		global $wpdb, $kwfdb;

		$sidebars = $kwfdb->select()->from(array('s' => $wpdb->prefix . 'kwf_sidebars'), array('*'))->query()->fetchAll();

		if (is_array($sidebars))
		{
			foreach ($sidebars as $sidebar)
			{
				unset($sidebar['ID']);
				$sidebar = array_stripslashes($sidebar);
				register_sidebar($sidebar);
			}
		}
	}
} ?>