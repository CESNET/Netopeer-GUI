<?php echo $view['form']->errors($form) ?>
<?php foreach ($form as $child) : ?>
    <?php echo $view['form']->row($child) ?>
<?php endforeach; ?>
