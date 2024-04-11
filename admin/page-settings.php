<div class="page-container autoai-settings">
    <h1>AutoAI General Settings</h1>


    <form method="post" action="">
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

        <input type="hidden" name="action" value="save_ai_settings" />
        <input type="submit" value="Save Setting">
    </form>



</div>