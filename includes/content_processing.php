<?php

define('API_KEY', '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');
define('API_ENDPOINT', 'https://api.openai.com/v1/chat/completions');


function sendToOpenAI($prompt) {
    try {
        $ch = curl_init(API_ENDPOINT);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . API_KEY
            ],
            CURLOPT_POSTFIELDS => json_encode([
                'model' => 'gpt-3.5-turbo-16k',
                'messages' => [['role' => 'user', 'content' => $prompt]]
            ])
        ]);

        $result = curl_exec($ch);
        if (curl_errno($ch)) {
            throw new Exception('Curl error in sendToOpenAI: ' . curl_error($ch));
        }
        curl_close($ch);

        return json_decode($result, true);
    } catch (Exception $e) {
        my_second_log('ERROR', $e->getMessage());
        return null;
    }
}

function getAiTitleAndExcerpt($contentAndTitle) {
    try {
        if (!isset($contentAndTitle['title'], $contentAndTitle['content'])) {
            throw new Exception('Invalid input array in getAiTitleAndExcerpt');
        }

        $prompt = <<<EOD
            Im giving you next a news article in the PC niche. 
            I need you to rewrite only the excerpt and title, the title should be very similar to the original, and I need you to return it in JSON format (only json), exactly like the example, without the "content". Remember to make a similar title as the original.
            So this is the original article:
            {
                "title": "{$contentAndTitle['title']}",
                "content": "{$contentAndTitle['content']}"
            }
            So, I need you to return ONLY json for title and excerpt, nothing else before or after, like this example:
            {
                "title": "",
                "excerpt": ""
            }
        EOD;

        $responseArray = sendToOpenAI($prompt);

        if (json_last_error() !== JSON_ERROR_NONE ||
            !isset($responseArray['choices'][0]['message']['content'])) {
            throw new Exception('Error decoding JSON in getAiTitleAndExcerpt or accessing content field');
        }

        $contentJsonString = $responseArray['choices'][0]['message']['content'];
        return json_decode($contentJsonString, true);
    } catch (Exception $e) {
        my_second_log('ERROR', $e->getMessage());
        return null;
    }
}



function getAiContent($contentAndTitle) {
    try {
        if (!isset($contentAndTitle['title'], $contentAndTitle['content'])) {
            throw new Exception('Invalid input array in getAiContent');
        }


        $prompt = <<<EOD
            I am providing a scraped article from the web for rewriting. Your task is to read the article, understand the content, ignore everything that is not about the main content of the article, rewrite the article in same lenght, expanding and enriching it while maintaining accuracy. And return back the content rewritten in HTML. Here are the specific instructions:

            - Exclude Unrelated Sections: Omit any 'related content', 'recommendations', 'suggested articles', or other parts not directly related to the main news story.

            - Handle Image Tags Carefully: Retain all existing image tags (<img src=...>) in their original positions, removing sources or credits associated with these images. However, do not create or infer any new image tags if they are not explicitly present in the original text.

            - Omit Descriptions of Videos: Any video descriptions within the article should be removed.

            Here's the original article:

            {
            "title": "{$contentAndTitle['title']}",
            "content": "{$contentAndTitle['content']}"
            }

            Leave existing <img> tags as they are, matching their position in the text, but removing any credits related to the image. Do not create or infer new image tags. Also, remove links to other articles and sections like 'related content', 'recommendations', 'suggested articles'.

            Please return only the rewritten main content of the article in HTML format, without any additional comments or explanations.
        EOD;

        $responseArray = sendToOpenAI($prompt);

        if (json_last_error() !== JSON_ERROR_NONE ||
            !isset($responseArray['choices'][0]['message']['content'])) {
            throw new Exception('Error decoding JSON getAiContent or accessing content field');
        }
        my_log($contentAndTitle['content']);
        my_log($responseArray);

        return $responseArray['choices'][0]['message']['content'];

    } catch (Exception $e) {
        my_second_log('ERROR', $e->getMessage());
        return null;
    }
}



function getAiContentOLDWORKING($contentAndTitle) {
    if (!isset($contentAndTitle['title'], $contentAndTitle['content'])) {
        my_log('Invalid input array in getAiContent');
        return null;
    }

    $prompt = <<<EOD
        I am providing a scraped news article from the PC technology niche for rewriting. Your task is to read the article, understand the content, extract and focus solely on the main content of the news, expanding and enriching it while maintaining accuracy. Specifically:
        
        - Exclude Unrelated Sections: Omit any 'related content', 'recommendations', 'suggested articles', or other parts not directly related to the main news story.
        
        - Handle Image Tags Carefully: Retain all existing image tags (<img src=...>) in their original positions, removing sources or credits associated with these images. However, do not create or infer any new image tags if they are not explicitly present in the original text. This includes not converting textual mentions of images (like '<span></span>(Image credit: Asus)') into image tags.
        
        - Omit Descriptions of Videos: Any video descriptions within the article should be removed.
        
        Here's the original article:

        {
        "title": "{$contentAndTitle['title']}",
        "content": "{$contentAndTitle['content']}"
        }

        Rewrite this article and subtitles, focusing on making the content longer and more informative, but do not use the same sentences. In the article, leave existing <img tags as they are, matching their position in the text, but removing any credits related to the image. Do not create or infer new image tags. Also, remove links to other articles and sections like 'related content', 'recommendations', 'suggested articles'.
        
        Please return only the rewritten main content in HTML format, without any additional comments or explanations.
    EOD;

    
    $responseArray = sendToOpenAI($prompt);

    // Check if json_decode was successful and the expected keys exist
    if (json_last_error() === JSON_ERROR_NONE &&
        isset($responseArray['choices'][0]['message']['content'])) {
        $theContent = $responseArray['choices'][0]['message']['content'];
        return $theContent;
    } else {
        my_log("Error decoding JSON getAiContent or accessing content field.");
        return null;
    }
}