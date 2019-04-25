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
            $values += array('is_private' => 0);
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
            $file_content = file_get_contents($filename);

            if (!mb_check_encoding($file_content, 'UTF-8')) {
                $file_content = mb_convert_encoding($file_content, 'UTF-8', 'pass');
            }

            $jsonObj = json_decode($file_content);

            if (is_null($jsonObj)) {
                $this->create($values, array('file' => array(t('Unable to parse JSON file. Error: %s', json_last_error_msg()))));
            } else {
                $values += array('name' => $jsonObj->name);

                $max_attachment_size = $this->helper->text->phpTobytes(get_upload_max_size());

                //creating the project
                $project_id = $this->createNewProject($values);

                if ($project_id > 0) {
                    //remove the columns created by default
                    $initial_columns = $this->columnModel->getAll($project_id);
                    foreach ($initial_columns as $column) {
                        $this->columnModel->remove($column['id']);
                    }

                    //getting columns from JSON file
                    foreach ($jsonObj->lists as $list) {
                        if ($list->closed) {
                            //ignore archived lists
                            continue;
                        }
                        //creating column
                        $column_id = $this->columnModel->create($project_id, $list->name, 0, '', 0);

                        //getting tasks from JSON file
                        foreach ($jsonObj->cards as $card) {
                            if ($card->closed) {
                                //ignore archived cards
                                continue;
                            }

                            //only get cards that belongs to this column
                            if ($card->idList == $list->id) {
                                //converting trello due date to kanboard format
                                $due_date = $card->due !== null ? date('Y-m-d H:i', strtotime($card->due)) : null;
                                $values = array(
                                    'title' => $card->name,
                                    'project_id' => $project_id,
                                    'column_id' => $column_id,
                                    'date_due' => $due_date,
                                    'description' => $card->desc,
                                );
                                //creating task
                                $task_id = $this->taskCreationModel->create($values);

                                if ($card->badges->checkItems > 0) {
                                    //getting checklists from JSON file
                                    foreach ($jsonObj->checklists as $checklist) {
                                        //only get checklists that belongs to this card
                                        if ($checklist->idCard == $card->id) {
                                            foreach ($checklist->checkItems as $checkitem) {
                                                $status = $checkitem->state == 'complete' ? 2 : 0;
                                                $values = array(
                                                    'title' => $checkitem->name,
                                                    'task_id' => $task_id,
                                                    'status' => $status,
                                                );
                                                //creating subtask
                                                $subtask_id = $this->subtaskModel->create($values);
                                            }
                                        }
                                    }
                                }

                                if ($card->badges->comments > 0) {
                                    //getting actions from JSON file
                                    foreach ($jsonObj->actions as $action) {
                                        //only get actions from commentCard type
                                        if ($action->type == 'commentCard') {
                                            //only get comments that belongs to this card
                                            if ($action->data->card->id == $card->id) {
                                                $values = array(
                                                    'task_id' => $task_id,
                                                    'user_id' => $this->userSession->getId(),
                                                    'comment' => $action->data->text,
                                                );
                                                //creating comment
                                                $comment_id = $this->commentModel->create($values);
                                            }
                                        }
                                    }
                                }

                                if ($card->badges->attachments > 0 and $this->is_trello_connected()) {
                                    //getting attachments from JSON file
                                    foreach ($card->attachments as $attachment) {
                                        $values = array(
                                            'task_id' => $task_id,
                                            'user_id' => $this->userSession->getId(),
                                        );
                                        //only get attachments that are uploaded files
                                        if ($attachment->isUpload) {
                                            $attachment_size = $this->retrieve_remote_file_size($attachment->url);

                                            if ($attachment_size < $max_attachment_size) {
                                                //here is the file we are downloading, replace spaces with %20
                                                $ch = curl_init($attachment->url);

                                                curl_setopt($ch, CURLOPT_TIMEOUT, 50);

                                                //return file in variable
                                                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                                                curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);

                                                $data = curl_exec($ch); //get curl response
                                                if ($data !== false) {
                                                    //creating attachment
                                                    $attachment_id = $this->taskFileModel->uploadContent($task_id, $attachment->name, base64_encode($data));
                                                }
                                                curl_close($ch);
                                            } else {
                                                // cant upload attachment, add a comment with attachment link
                                                $values += array('comment' => t('Attachment exceeds the upload limit: %s', $attachment->url));
                                                //creating comment
                                                $comment_id = $this->commentModel->create($values);
                                            }
                                        } else {
                                            // just an url, add a comment
                                            $values += array('comment' => t('Attachment is just a link: %s', $attachment->url));
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

        if ($response) return true;

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
