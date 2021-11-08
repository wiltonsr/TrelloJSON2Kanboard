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
            'trello_connection' => $this->is_trello_connected(),
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
            $file_content = file_get_contents($filename);

            if (!mb_check_encoding($file_content, 'UTF-8')) {
                $file_content = mb_convert_encoding($file_content, 'UTF-8', 'pass');
            }

            $jsonObj = json_decode($file_content);

            if (is_null($jsonObj)) {
                $this->create($values, array('file' => array(t('Unable to parse JSON file. Error: %s', json_last_error_msg()))));
            } else {
                $project = $this->trelloJSON2KanboardModel->parserJSON($jsonObj);

                $values += array(
                    'name' => $project->name,
                );

                $max_attachment_size = $this->helper->text->phpTobytes(get_upload_max_size());

                //creating the project
                $project_id = $this->createNewProject($values);

                if ($project_id > 0) {
                    //remove the columns created by default
                    $initial_columns = $this->columnModel->getAll($project_id);
                    foreach ($initial_columns as $column) {
                        $this->columnModel->remove($column['id']);
                    }

                    foreach ($project->columns as $column) {
                        //creating column
                        $column_id = $this->columnModel->create($project_id, $column->name, 0, '', 0);

                        foreach ($column->tasks as $task) {
                            $values = array(
                                'title' => $task->name,
                                'project_id' => $project_id,
                                'column_id' => $column_id,
                                'date_due' => $task->date_due,
                                'description' => $task->desc,
                            );
                            //creating task
                            $task_id = $this->taskCreationModel->create($values);

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
                                $values = array(
                                    'task_id' => $task_id,
                                    'user_id' => $this->userSession->getId(),
                                    'comment' => $comment->content,
                                );
                                //creating comment
                                $comment_id = $this->commentModel->create($values);
                            }

                            if (sizeof($task->attachments) > 0 && $this->is_trello_connected()) {
                                foreach ($task->attachments as $attachment) {
                                    $values = array(
                                        'task_id' => $task_id,
                                        'user_id' => $this->userSession->getId(),
                                    );
                                    //only get attachments that are uploaded files
                                    $attachment_size = $this->retrieve_remote_file_size($attachment->url);
                                    if ($attachment_size < $max_attachment_size) {
                                        //here is the file we are downloading, replace spaces with %20
                                        $ch = curl_init($attachment->url);
                                        curl_setopt($ch, CURLOPT_TIMEOUT, 50);
                                        //return file in variable
                                        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                                        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
                                        curl_setopt($ch, CURLINFO_HEADER_OUT, true);
                                        $data = curl_exec($ch); //get curl response
                                        $status_code = curl_getinfo($ch, CURLINFO_HTTP_CODE); //get curl response http status code
                                        /*
                                         *TODO: add support for download attachments from Trello
                                         *      currently file download requests return 401
                                        */
                                        if ($data !== false && ($status_code>=200 && $status_code<300)) {
                                            //creating attachment
                                            $attachment_id = $this->taskFileModel->uploadContent($task_id, $attachment->filename, base64_encode($data));
                                        } else {
                                            // cant upload attachment, add a comment with infos
                                            $values += array('comment' => t('Cant Download Attachment: %s Link: %s Http Response Status Code: %d', $attachment->filename, $attachment->url, $status_code));
                                            //creating comment
                                            $comment_id = $this->commentModel->create($values);
                                        }
                                        curl_close($ch);
                                    } else {
                                        // cant upload attachment, add a comment with attachment link
                                        $values += array('comment' => t('Attachment exceeds the upload limit: %s', $attachment->url));
                                        //creating comment
                                        $comment_id = $this->commentModel->create($values);
                                    }
                                }
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
            'is_trello_imported' => 1,
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

    //returns true, if can connect to Trello attachments url, false if not
    private function is_trello_connected()
    {
        static $url = 'https://trello-attachments.s3.amazonaws.com';

        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            return false;
        }

        //initialize curl
        $curlInit = curl_init($url);
        curl_setopt($curlInit, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($curlInit, CURLOPT_HEADER, true);
        curl_setopt($curlInit, CURLOPT_NOBODY, true);
        curl_setopt($curlInit, CURLOPT_RETURNTRANSFER, true);

        //get answer
        $response = curl_exec($curlInit);

        curl_close($curlInit);

        if ($response) {
            return true;
        }

        return false;
    }

    //retrieve file size without download it
    private function retrieve_remote_file_size($url)
    {
        $ch = curl_init($url);

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        curl_setopt($ch, CURLOPT_HEADER, TRUE);
        curl_setopt($ch, CURLOPT_NOBODY, TRUE);

        $data = curl_exec($ch);
        $size = curl_getinfo($ch, CURLINFO_CONTENT_LENGTH_DOWNLOAD);

        curl_close($ch);
        return $size;
    }
}
