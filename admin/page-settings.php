<div class="page-container autoai-settings">
    <h1>AI Rewriter</h1>

    <form method="post" action="">
        <h2>Settings</h2>
        <label for="open_ai_key">OpenAI API Key</label><br>
        <input type="text" name="open_ai_key" placeholder="sk-..." value="<?php echo esc_attr(get_option('open_ai_key_option')); ?>" /><br><br>
            
        <label for="output_language">Output language</label><br>
        <select name="output_language">
            <?php
                $languages = [
                    "English",
                    "Mandarin Chinese",
                    "Hindi",
                    "Spanish",
                    "French",
                    "Modern Standard Arabic",
                    "Bengali",
                    "Portuguese",
                    "Russian",
                    "Urdu",
                    "Indonesian",
                    "German",
                    "Swahili",
                    "Marathi",
                    "Serbian",
                    "Telugu",
                    "Turkish",
                    "Tamil",
                    "Yoruba",
                    "Italian",
                    "Thai",
                ];
                $savedLanguage = get_option('autoai_output_language', '');
                foreach ($languages as $language) {
                    if($savedLanguage == $language) {
                        echo '<option value="' . $language . '" selected>' . $language . '</option>';
                    }
                    else {
                        echo '<option value="' . $language . '">' . $language . '</option>';
                    }
                }
            ?>
        </select><br><br>

        <?php
        $times_a_day_run_cron = get_option('times_a_day_run_cron', '');
        ?>

        <label>Cron job interval to check for new articles</label><br>
        <select name="times_a_day_run_cron">
            <option value="daily" <?php if($times_a_day_run_cron == 'daily') echo 'selected';  ?>>Daily</option>
            <option value="hourly" <?php if($times_a_day_run_cron == 'hourly') echo 'selected';  ?>>Hourly</option>
            <option value="twicedaily" <?php if($times_a_day_run_cron == 'twicedaily') echo 'selected';  ?>>Twicedaily</option>
            <option value="weekly" <?php if($times_a_day_run_cron == 'weekly') echo 'selected';  ?>>Weekly</option>
        </select><br><br>

        <input type="hidden" name="action" value="save_ai_settings" />
        <input type="submit" value="Save Setting">
    </form>

    <h2>Scheduled</h2>
    <?php
        require_once dirname(plugin_dir_path( __FILE__ )) . '/includes/classes/clsScheduling.php';
        $sched = new Scheduling();
    ?>

    <?php $sched->display_scheduled_time(); ?>

    <h4>Active scheduled sources: </h4>
    <?php $sched->display_scheduled_sources(); ?>



</div>