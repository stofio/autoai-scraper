<div class="page-container">
    <h1>AI Auto Post Settings</h1>
    <div class="white-container">

        <form method="post" action="" style="margin-bottom:30px">
            <label for="open_ai_key">OpenAI API Key</label><br>
            <input type="text" name="open_ai_key" placeholder="sk-..." value="<?php echo esc_attr(get_option('open_ai_key_option')); ?>" />
            
            <input type="hidden" name="action" value="save_open_ai_key" />
            <input type="submit" value="Save Key">
        </form>


        <form method="post" action="" style="margin-bottom:30px">
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
            </select>
            <input type="hidden" name="action" value="save_output_language" />
            <input type="submit" value="Save Language">
        </form>
    
    </div>

    <h2>AI settings</h2>
    <form method="post" action="" style="margin-bottom:30px">
        <?php
            $ai_model = get_option('open_ai_model', '');
            $word_count = get_option('word_count_per_open_ai_request', '');
            $prompt_partial_text = get_option('prompt_partial_text', '');
        ?>

        <label>Prompt (placeholders: {$lang}, {$contentPart}, {$title})</label><br>
        <button type="button" id="resetPrompt">Reset prompt</button><br>
        <script>
            jQuery(document).ready(function($) {
                var originalPrompt = 
`Rewrite in {$lang} the provided article excerpt, preserving its original meaning, structure, and HTML formatting. 
Expand the content's depth while omitting unrelated details. 
Exclude references to videos, credits, unrelated sections and unrelated text that is not part of the article context. The goal is a longer, enriched version of the text, focused solely on the article's content. Remove any parts that are not part of the story of the article, like related links, readings, recommendations...

Here is the article excerpt to rewrite:
"content": "{$contentPart}"

Some of the "content" (or all of the given) is out of context from the article '{$title}', exclude that part.
Remember to exclude videos related text, credits, and unrelated sections like 'releated readings', 'editor recommendations' sections with its content, ecc.
The output should be a detailed, HTML-formatted text in {$lang} language that mirrors the original's key points of the entire article, with longer pharagraphs.`;

                $('#resetPrompt').on('click', () => {
                    if(!confirm('Your changes will be deleted...')) return;
                    $('textarea[name="prompt_partial_text"]').val(originalPrompt);
                });
            });
        </script>
        <textarea name="prompt_partial_text" style="width: 90%; height:240px;font-size:13px"><?php echo stripslashes($prompt_partial_text) ?></textarea><br><br>

        <label for="open_ai_model">Model</label>
        <select name="open_ai_model">
            <option value="gpt-3.5-turbo" <?php if($ai_model == 'gpt-3.5-turbo') echo 'selected'  ?>>gpt-3.5-turbo</option>
            <option value="gpt-3.5-turbo-16k" <?php if($ai_model == 'gpt-3.5-turbo-16k') echo 'selected'  ?>>gpt-3.5-turbo-16k</option>
            <option value="gpt-4.0" <?php if($ai_model == 'gpt-4.0') echo 'selected'  ?>>gpt-4 (NOTE: expensive)</option>
        </select><br><br>

        <label for="word_count_per_open_ai_request">Word count per request (for 600 or greater words use gpt-3.5-turbo-16k)</label><br>
        <select name="word_count_per_open_ai_request">
            <option value="200" <?php if($word_count == '200') echo 'selected';  ?>>200</option>
            <option value="300" <?php if($word_count == '300') echo 'selected';  ?>>300</option>
            <option value="400" <?php if($word_count == '400') echo 'selected';  ?>>400</option>
            <option value="500" <?php if($word_count == '500') echo 'selected';  ?>>500</option>
            <option value="600" <?php if($word_count == '600') echo 'selected';  ?>>600</option>
            <option value="700" <?php if($word_count == '700') echo 'selected';  ?>>700</option>
            <option value="800" <?php if($word_count == '800') echo 'selected';  ?>>800</option>
        </select><br><br>


        <input type="hidden" name="action" value="save_ai_settings" />
        <input type="submit" value="Save Setting">
    </form>
</div>