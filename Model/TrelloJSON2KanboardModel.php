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

            foreach ($jsonObj->cards as $card) {
                if ($card->closed) {
                    //ignore archived lists
                    continue;
                }
                if ($card->idList == $column->trello_id) {
                    $due_date = $card->due !== null ? date('Y-m-d H:i', strtotime($card->due)) : null;
                    $task = new Task($card->name, $card->id, $card->idList, $due_date, $card->desc);
                    array_push($column->tasks, $task);

                    //getting checklists from JSON file
                    foreach ($jsonObj->checklists as $checklist) {
                        if ($checklist->idCard == $card->id) {
                            foreach ($checklist->checkItems as $checkitem) {
                                $status = $checkitem->state == 'complete' ? 2 : 0;
                                //creating subtask
                                $subtask = new Subtask($checkitem->name, $status);
                                array_push($task->subtasks, $subtask);
                            }
                        }
                    }

                    //getting actions from JSON file
                    foreach ($jsonObj->actions as $action) {
                        //only get actions from commentCard type
                        if ($action->type == 'commentCard') {
                            //only get comments that belongs to this card
                            if ($action->data->card->id == $task->trello_id) {
                                $comment = new Comment($action->data->text);
                                array_push($task->comments, $comment);
                            }
                        }
                    }
                }
            }
        }


        return $project;
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
    var $tasks = array();

    function __construct($name, $trello_id)
    {
        $this->name = $name;
        $this->trello_id = $trello_id;
    }
}

class Task
{
    var $name;
    var $trello_id;
    var $trello_column_id;
    var $date_due;
    var $desc;
    var $subtasks = array();
    var $comments = array();

    function __construct($name, $trello_id, $trello_column_id, $date_due, $desc)
    {
        $this->name = $name;
        $this->trello_id = $trello_id;
        $this->trello_column_id = $trello_column_id;
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
