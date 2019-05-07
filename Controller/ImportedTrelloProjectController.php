<?php

namespace Kanboard\Plugin\TrelloJSON2Kanboard\Controller;

use Kanboard\Controller\BaseController;

/**
 * ImportedTrelloProjectController Controller
 *
 * @package  Kanboard\Plugin\TrelloJSON2Kanboard\Controller
 * @author   Wilton Rodrigues
 */
class ImportedTrelloProjectController extends BaseController
{
    public function show()
    {
        if ($this->userSession->isAdmin()) {
            $projects = $this->importedTrelloProjectModel->getAllTrelloImportedProjects();
        } else {
            $projects = $this->importedTrelloProjectModel->getTrelloImportedProjectsByUser($this->userSession->getId());
        }

        // $this->logger->info('Projects IDs: '.print_r($projects));

        $query = $this->projectModel->getQueryByProjectIds($projects);

        $paginator = $this->paginator
            ->setUrl('ImportedTrelloProjectController', 'show', array('plugin' => 'TrelloJSON2Kanboard'))
            ->setMax(20)
            ->setOrder('name')
            ->setQuery($query)
            ->calculate();

        $this->response->html($this->helper->layout->app('TrelloJSON2Kanboard:trello_imported/listing', array(
            'paginator'   => $paginator,
            'title'       => t('Trello Imported Projects') . ' (' . $paginator->getTotal() . ')',
        )));
    }
}
