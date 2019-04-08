<?php

namespace Kanboard\Plugin\TrelloJSON2Kanboard\Controller;

use Kanboard\Controller\BaseController;

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
        if (!isset($values['is_private'])) {
            $values +=  array('is_private' => 0);
        }
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

        if ($this->configModel->get('disable_private_project') == 1) {
            $values = array('is_private' => 0);
        }

        if (!file_exists($filename)) {
            $this->create($values, array('file' => array(t('Please select a JSON file.'))));
        } else {
            $jsonObj = json_decode(file_get_contents($filename));

            if (is_null($jsonObj)) {
                $this->create($values, array('file' => array(t('Unable to parse JSON file. Error: %s', json_last_error_msg()))));
            } else {
                $project = $this->trelloJSON2KanboardModel->parserJSON($jsonObj);

                $values += array('name' => $project->name);

                //creating the project
                $project_id = $this->createNewProject($values);

                if ($project_id > 0) {
                    //remove the columns created by default
                    $initial_columns = $this->columnModel->getAll($project_id);
                    foreach ($initial_columns as $column) {
                        $this->columnModel->remove($column['id']);
                    }

                    //getting columns from JSON file
                    foreach ($project->columns as $column) {
                        //creating column
                        $column_id = $this->columnModel->create($project_id, $column->name, 0, '', 0);

                        //getting tasks from JSON file
                        foreach ($column->tasks as $task) {
                            //only get cards that belongs to this column
                            $values = array(
                                'title' => $task->name,
                                'project_id' => $project_id,
                                'column_id' => $column_id,
                                'date_due' => $task->date_due,
                                'description' => $task->desc,
                            );
                            //creating task
                            $task_id = $this->taskCreationModel->create($values);

                            //getting checklists from JSON file
                            foreach ($task->subtasks as $subtask) {
                                $values = array(
                                    'title' => $subtask->content,
                                    'task_id' => $task_id,
                                    'status' => $subtask->status,
                                );
                                //creating subtask
                                $subtask_id = $this->subtaskModel->create($values);
                            }

                            foreach ($task->comments as $comment) {
                                //only get actions from commentCard type
                                $values = array(
                                    'task_id' => $task_id,
                                    'user_id' => $this->userSession->getId(),
                                    'comment' => $comment->content,
                                );
                                //creating comment
                                $comment_id = $this->commentModel->create($values);
                            }
                        }
                    }
                }

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

    public function json_last_error_msg()
    {
        static $errors = array(
            JSON_ERROR_NONE             => null,
            JSON_ERROR_DEPTH            => 'Maximum stack depth exceeded',
            JSON_ERROR_STATE_MISMATCH   => 'Underflow or the modes mismatch',
            JSON_ERROR_CTRL_CHAR        => 'Unexpected control character found',
            JSON_ERROR_SYNTAX           => 'Syntax error, malformed JSON',
            JSON_ERROR_UTF8             => 'Malformed UTF-8 characters, possibly incorrectly encoded'
        );
        $error = json_last_error();
        return array_key_exists($error, $errors) ? $errors[$error] : 'Unknown error ({$error})';
    }
}
