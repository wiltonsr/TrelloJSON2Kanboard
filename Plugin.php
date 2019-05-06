<?php

namespace Kanboard\Plugin\TrelloJSON2Kanboard;

use Kanboard\Core\Plugin\Base;
use Kanboard\Core\Translator;

class Plugin extends Base
{
    public function initialize()
    {
        $this->template->hook->attach('template:dashboard:page-header:menu', 'TrelloJSON2Kanboard:dashboard/menu');
        $this->template->hook->attach('template:header:creation-dropdown', 'TrelloJSON2Kanboard:dashboard/menu');
    }

    public function onStartup()
    {
        Translator::load($this->languageModel->getCurrentLanguage(), __DIR__ . '/Locale');
    }

    public function getClasses()
    {
        return array(
            'Plugin\TrelloJSON2Kanboard\Model' => array(
                'TrelloJSON2KanboardModel',
            )
        );
    }

    public function getPluginName()
    {
        return 'TrelloJSON2Kanboard';
    }

    public function getPluginDescription()
    {
        return t('Plugin for Importing Trello Projects from JSON Files to Kanboard.');
    }

    public function getPluginAuthor()
    {
        return 'Wilton Rodrigues';
    }

    public function getPluginVersion()
    {
        return '1.2.1';
    }

    public function getPluginHomepage()
    {
        return 'https://github.com/wiltonsr/TrelloJSON2Kanboard';
    }
}
