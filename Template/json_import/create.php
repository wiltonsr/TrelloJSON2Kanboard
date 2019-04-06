<section id="main">
    <div class="page-header">
        <h2><?= t('New Import Trello Project (JSON File)') ?></h2>
    </div>

    <form id="project-import-form" action="<?= $this->url->href('TrelloJSON2KanboardController', 'save', array('plugin' => 'TrelloJSON2Kanboard')) ?>" method="post" enctype="multipart/form-data">
        <?= $this->form->csrf() ?>

        <?= $this->form->label(t('Trello JSON File'), 'file') ?>
        <?= $this->form->file('file', $errors) ?>
        <p class="form-help"><?= t('Maximum size: ') ?><?= is_integer($max_size) ? $this->text->bytes($max_size) : $max_size ?></p>

        <?php if ($this->app->config('disable_private_project') != 1) : ?>
            <?php if ($this->user->hasProjectAccess('ProjectCreationController', 'create', $project['id'])) : ?>
                <?= $this->form->checkbox('is_private', t('Public project'), 0, $values['is_private'] == 0) ?>
                <p class="form-help"><?= t('Only public projects have users and groups management.') ?></p>
            <?php endif ?>
        <?php endif ?>


        <div class="panel">
            <h3><?= t('Instructions') ?></h3>
            <ul>
                <li><?= t('Your file must use the predefined CSV format') ?></li>
                <li><?= t('Your file must be encoded in UTF-8') ?></li>
                <li><?= t('The first row must be the header') ?></li>
                <li><?= t('Duplicates are not verified for you') ?></li>
                <li><?= t('The due date must use the ISO format: YYYY-MM-DD') ?></li>
                <li><?= t('Tags must be separated by a comma') ?></li>
                <li><?= t('Only the task title is required') ?></li>
            </ul>
            <p class="margin-top">
                <?= $this->modal->submitButtons(array('submitLabel' => t('Import'))) ?>
            </p>
        </div>
    </form>

</section>