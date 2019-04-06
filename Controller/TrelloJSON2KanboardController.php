<?php

namespace Kanboard\Plugin\TrelloJSON2Kanboard\Controller;

use Kanboard\Controller\BaseController;
use Kanboard\Model\ProjectModel;
use Kanboard\Model\ColumnModel;
use Kanboard\Model\TaskModel;
use Kanboard\Model\SubtaskModel;

/**
 * TrelloJSON2Kanboard Controller
 *
 * @package  Kanboard\Plugin\TrelloJSON2Kanboard\Controller
 * @author   Wilton Rodrigues
 */
class TrelloJSON2KanboardController extends BaseController
{
    public function create(array $values = array(), array $errors = array())
    {
        $this->response->html($this->helper->layout->app('TrelloJSON2Kanboard:json_import/create', array(
            'values' => $values,
            'errors' => $errors,
            'max_size' => get_upload_max_size(),
        )));
    }

    /**
     * Process JSON file
     */
    public function save()
    {
        $values = $this->request->getValues() + array('is_private' => 1);
        $filename = $this->request->getFilePath('file');

        if (!file_exists($filename)) {
            $this->create($values, array('file' => array(t('Please select a JSON file.'))));
        } else {
            $jsonObj = json_decode(file_get_contents($filename), true);

            $values += array('name' => $jsonObj['name']);

            $project_id = $this->createNewProject($values);

            if ($project_id > 0) {
                $this->flash->success(t('Your project have been imported successfully.'));
                return $this->response->redirect($this->helper->url->to('ProjectViewController', 'show', array('project_id' => $project_id)));
            }

            $this->flash->failure(t('Unable to import your project.'));
        }
    }

    /**
     * Save a new project
     *
     * @access private
     * @param  array  $values
     * @return boolean|integer
     */
    private function createNewProject(array $values)
    {
        $project = array(
            'name' => $values['name'],
            'is_private' => $values['is_private'],
        );

        return $this->projectModel->create($project, $this->userSession->getId(), true);
    }
}
