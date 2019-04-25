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
            <?= $this->form->checkbox('is_private', t('Public project'), 0, $values['is_private'] == 0) ?>
            <p class="form-help"><?= t('Only public projects have users and groups management.') ?></p>
        <?php endif ?>


        <div class="panel">
            <h3><?= t('Observations') ?></h3>
            <ul>
                <li><?= t('Your file must use the JSON format') ?></li>
                <li><?= t('The JSON file must contain the keys: name, lists, cards, checklists and actions') ?></li>
                <li><?= t('Your user will be the author of the imported comments') ?></li>
                <li><?= t('Your attachments that are links will be imported as comments') ?></li>
            </ul>
            <p class="margin-top">
                <?= $this->modal->submitButtons(array('submitLabel' => t('Import'))) ?>
            </p>
        </div>
    </form>

</section>