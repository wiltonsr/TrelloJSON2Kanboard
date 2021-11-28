<?php

namespace Kanboard\Plugin\TrelloJSON2Kanboard\Model;

use Kanboard\Core\Base;
use Kanboard\Model\ProjectModel;
use Kanboard\Model\ProjectUserRoleModel;

/**
 * ImportedTrelloProject Model
 *
 * @package  Kanboard\Plugin\TrelloJSON2Kanboard\Model
 * @author   Wilton Rodrigues
 */
class ImportedTrelloProjectModel extends Base
{
      /**
       * SQL table name for projects
       *
       * @var string
       */
      const TABLE = 'projects';

      /**
       * Value for active project
       *
       * @var integer
       */
      const TRELLO_IMPORTED = 1;


      /**
       * Get all Trello Imported project ids
       *
       * @access public
       * @return array
       */
      public function getAllTrelloImportedProjects()
      {
            return $this->db
                  ->table(self::TABLE)
                  ->asc('name')
                  ->eq('is_trello_imported', self::TRELLO_IMPORTED)
                  ->findAllByColumn('id');
      }

      public function getTrelloImportedProjectsByUser($user_id, $status = array(ProjectModel::ACTIVE, ProjectModel::INACTIVE))
      {
          $userProjects = $this->db
              ->hashtable(ProjectModel::TABLE)
              ->eq(ProjectUserRoleModel::TABLE.'.user_id', $user_id)
              ->eq('is_trello_imported', self::TRELLO_IMPORTED)
              ->in(ProjectModel::TABLE.'.is_active', $status)
              ->join(ProjectUserRoleModel::TABLE, 'project_id', 'id')
              ->findAllByColumn('id');

          $groupProjects = $this->projectGroupRoleModel->getProjectsByUser($user_id, $status);
          $projects = $userProjects + $groupProjects;

          asort($projects);

          return $projects;
      }
}
