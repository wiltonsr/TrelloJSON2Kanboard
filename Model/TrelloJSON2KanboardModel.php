<?php

namespace Kanboard\Plugin\TrelloJSON2Kanboard\Model;

use Kanboard\Core\Base;

/**
 * TrelloJSON2Kanboard Model
 *
 * @package  Kanboard\Plugin\TrelloJSON2Kanboard\Model
 * @author   Wilton Rodrigues
 */
class TrelloJSON2KanboardModel extends Base
{
    public function parserJSON($jsonObj)
    {
        $project = new Project($jsonObj->name);

        //getting columns from JSON file
        foreach ($jsonObj->lists as $list) {
            if ($list->closed) {
                //ignore archived lists
                continue;
            }
            //creating column
            $column = new Column($list->name, $list->id);
            array_push($project->columns, $column);
        }

        foreach ($jsonObj->cards as $card) {
            if ($card->closed) {
                //ignore archived lists
                continue;
            }
            $due_date = $card->due !== null ? date('Y-m-d H:i', strtotime($card->due)) : null;
            $task = new Task($card->name, $card->id, $due_date, $card->desc);
            $column_id = $this->card_column_id($project->columns, $card->idList);
            if (!is_null($column_id)) {
                array_push($project->columns[$column_id]->tasks, $task);
            }

            if ($card->badges->attachments > 0) {
                foreach ($card->attachments as $att) {
                    //only get attachments that are uploaded files
                    if ($att->isUpload) {
                        $attachment = new Attachment($att->name, $att->url);
                        array_push($task->attachments, $attachment);
                    } else {
                        // just an url, add a comment
                        $comment = new Comment(t('Attachment is just a link: %s', $att->url));
                        array_push($task->comments, $comment);
                    }
                }
            }
        }

        //getting actions from JSON file
        foreach ($jsonObj->actions as $action) {
            //only get actions from commentCard type
            if ($action->type == 'commentCard') {
                //only get comments that belongs to this card
                $values = $this->comment_card_id($project->columns, $action->data->card->id);
                $comment = new Comment($action->data->text);
                if (!is_null($values)) {
                    array_push($project->columns[$values['column_key']]->tasks[$values['task_key']]->comments, $comment);
                }
            }
        }

        foreach ($jsonObj->checklists as $checklist) {
            //only get checklists that belongs to this card
            $values = $this->checkitem_card_id($project->columns, $checklist->idCard);
            if (!is_null($values)) {
                foreach ($checklist->checkItems as $checkitem) {
                    $status = $checkitem->state == 'complete' ? 2 : 0;
                    $subtask = new Subtask($checkitem->name, $status);
                    array_push($project->columns[$values['column_key']]->tasks[$values['task_key']]->subtasks, $subtask);
                }
            }
        }

        return $project;
    }

    public function card_column_id($columns, $value)
    {
        foreach ($columns as $task_key => $card) {
            if ($card->trello_id == $value) {
                return $task_key;
            }
        }
    }

    public function comment_card_id($columns, $value)
    {
        foreach ($columns as $column_key => $column) {
            foreach ($column->tasks as $task_key => $task) {
                if ($task->trello_id == $value) {
                    return array(
                        'column_key' => $column_key,
                        'task_key' => $task_key,
                    );
                }
            }
        }
    }

    public function checkitem_card_id($columns, $value)
    {
        foreach ($columns as $column_key => $column) {
            foreach ($column->tasks as $task_key => $task) {
                if ($task->trello_id == $value) {
                    return array(
                        'column_key' => $column_key,
                        'task_key' => $task_key,
                    );
                }
            }
        }
    }
}

class Project
{
    public $name;
    public $columns = array();

    public function __construct($name)
    {
        $this->name = $name;
    }
}

class Column
{
    var $name;
    var $trello_id;
    var $kanboard_id;
    var $tasks = array();

    function __construct($name, $trello_id, $kanboard_id = null)
    {
        $this->name = $name;
        $this->trello_id = $trello_id;
        $this->kanboard_id = $kanboard_id;
    }
}

class Task
{
    var $name;
    var $trello_id;
    var $date_due;
    var $desc;
    var $subtasks = array();
    var $comments = array();
    var $attachments = array();

    function __construct($name, $trello_id, $date_due, $desc)
    {
        $this->name = $name;
        $this->trello_id = $trello_id;
        $this->date_due = $date_due;
        $this->desc = $desc;
    }
}

class Subtask
{
    var $content;
    var $status;

    function __construct($content, $status)
    {
        $this->content = $content;
        $this->status = $status;
    }
}

class Comment
{
    var $content;

    function __construct($content)
    {
        $this->content = $content;
    }
}

class Attachment
{
    var $filename;
    var $url;

    function __construct($filename, $url)
    {
        $this->filename = $filename;
        $this->url = $url;
    }
}
