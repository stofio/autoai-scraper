<?php

define('API_KEY', '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');
define('API_ENDPOINT', 'https://api.openai.com/v1/chat/completions');


function generateContentWithAI($article) {
    $titleAndExcerpt = getAiTitleAndExcerpt($article);

    // Check if title and excerpt were successfully generated
    if ($titleAndExcerpt === 'error') {
        return 'error'; // Propagate the error
    }

    //$content = getAiContent($article);

    $content = rewriteContent($article['content'], $article['title']);

    // Check if content was successfully generated
    if ($content === 'error') {
        return 'error'; // Propagate the error
    }

    // Return the combined AI-generated content
    return [
        'title' => $titleAndExcerpt['title'],
        'excerpt' => $titleAndExcerpt['excerpt'],
        'content' => $content,
        'img-url' => $article['img-url'],
        'img-credit' => $article['img-credit']
    ];
}


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




function rewriteContent($content, $title) {
    $dom = new DOMDocument();
    libxml_use_internal_errors(true);
    $dom->loadHTML(mb_convert_encoding($content, 'HTML-ENTITIES', 'UTF-8'));
    libxml_clear_errors();

    $body = $dom->getElementsByTagName('body')->item(0);
    $currentPart = '';
    $currentWordCount = 0;
    $rewrittenContent = '';
    $lastNode = null;  // Variable to store the reference to the last node
    $nonPharNode = null;

    foreach ($body->childNodes as $node) {

        if ($node->nodeName === 'p') {
            $paragraphText = $node->textContent;
            $wordCount = str_word_count($paragraphText);

            if ($currentWordCount + $wordCount > 300) {
                // Check if last node was a heading
                if ($lastNode && in_array($lastNode->nodeName, ['h1', 'h2', 'h3', 'h4', 'h5', 'h6'])) {
                    $detachedHeading = $lastNode->parentNode->removeChild($lastNode);
                }

                // Process the current part
                $imagePositions = calculateImagePositionsByTags($currentPart);
                $rewrittenPart = getAIPieceOfArticle($currentPart, $title);
                $finalContent = reinsertImagesByTagCount($rewrittenPart, $imagePositions);

                /*my_log('ORIGINAL');
                my_log('ORIGINAL');
                my_log('ORIGINAL');
                my_log($currentPart);
                my_log('REWRITTEN');
                my_log('REWRITTEN');
                my_log('REWRITTEN');
                my_log($rewrittenPart);*/

                $rewrittenContent .= $finalContent;

                // Prepare for the next iteration
                $currentPart = $detachedHeading ? $dom->saveHTML($detachedHeading) : '';
                $currentPart .= $dom->saveHTML($node);
                $currentWordCount = $wordCount;
            } else {
                //first add the stored non-phar element 
                if($nonPharNode) {
                   $currentPart .= $dom->saveHTML($nonPharNode);
                   $nonPharNode = null; 
                }
                // then Add the current paragraph to the part
                $currentPart .= $dom->saveHTML($node);
                $currentWordCount += $wordCount;
                
            }
        } else {
            // For non-paragraph elements (usually h2 h3), store it as $nonPharNode before adding
            $nonPharNode = $node;

            //just append them to the current part
            //$currentPart .= $dom->saveHTML($node);

        }

        // Update the last node reference
        $lastNode = $node;
    }

    // Process the last part if it's not empty
    if (!empty($currentPart)) {
        $imagePositions = calculateImagePositionsByTags($currentPart);
        $rewrittenPart = getAIPieceOfArticle($currentPart, $title);
        $finalContent = reinsertImagesByTagCount($rewrittenPart, $imagePositions);
        $rewrittenContent .= $finalContent;
    }

    return $rewrittenContent;
}



function getAIPieceOfArticle($contentPart, $title) {
    try {
        if (empty($contentPart)) {
            throw new Exception('Content part is empty in getAIPieceOfArticle');
        }

        $prompttt = <<<EOD
            I am providing a portion of an article scraped from the web for rewriting. This is a part of a larger article. Your task is to read this part and thoroughly rewrite it and restructure it. The rewritten version should be as long or longer than the original, with an emphasis on expanding and enriching the content while maintaining accuracy. Keep all the HTML images tags unchanged. Also be carefull to ignore into the rewritten content all links and unrelated information and data to the article point (like 'related links', captions, author names, credits). Here are the specific instructions:

            - Expand, restructure and Enrich Content: Ensure the rewritten part matches or exceeds the word count of the original. Actively expand on points by providing additional context, detailed explanations, relevant examples, and supplementary information that enhances the depth and breadth of the content.

            - Maintain Essential Information: Preserve all key information and facts from the original part. Each point in the original should be clearly reflected and expanded upon in the rewritten version and if possible reordering it.

            - Handle Image Tags with Precision: Keep any existing image tags (<img src=...>) in their original positions within the text. Remove any sources or credits associated with these images. Do not create or infer new image tags.

            - Remove Video Descriptions: Exclude any descriptions or content related to videos in the original article.

            - Remove author names, credits and unrelated parts to the article.

            Here's the original content part:

            {
            "content": "{$contentPart}"
            }

            Retain existing <img> tags in their exact positions, removing image credits. 
            Also, remove links to other articles and sections like 'related content', 'recommendations', 'suggested articles'. 
            Your goal is to return a comprehensively rewritten version of this part of the content in HTML format. This version should be as extensive as or more detailed than the original, without any additional comments or explanations.
        EOD;


        $prompdddt = <<<EOD
            In this task we are rewriting a piece of an entire article, focus only on this piece. Please rewrite the provided part of the article while maintaining the original meaning and structure. Ensure the rewritten text is more lengthy and style as the original, following the same HTML tags structure. Follow these guidelines:

            - Aim for a rewritten version that expands the depth of the content, keeping essential information and omitting extraneous details.
            - Preserve the html structural.
            - Do not include additional comments, links, credits, or unrelated content to the article.
            - Exclude any references to videos, author names, credits, and unrelated sections like 'related links', 'Recommendations' or 'suggestions'.
            

            Here is the part of the article to be rewritten and to expand:
            "content": "{$contentPart}"

            The expected output is a rewritten text in HTML format, accurately reflecting the original's points, making a longer text, and adhering to the specified requirements. Don't forget that this is just a piece of the entire article.
        EOD;

        $prompt = <<<EOD
        Rewrite the provided article excerpt, preserving its original meaning, structure, and HTML formatting. 
        Expand the content's depth while omitting unrelated details. 
        Exclude references to videos, author names, credits, authors, unrelated sections and unrelated text that does not make sense in the context. The goal is a longer, enriched version of the text, focused solely on the article's content. Remove any parts that are not part of the story of the article, like related links, readings, recommendations...

        This is the title of the main article: "{$title}"
        Here is the article excerpt to rewrite:
        "content": "{$contentPart}"

        Remember to exclude videos related text, author names, credits, and unrelated sections like 'releated readings', 'editor recommendations' sections, ecc.
        The output should be a detailed, HTML-formatted text that mirrors the original's key points of the entire article, with longer pharagraphs.
        EOD;


        $responseArray = sendToOpenAI($prompt);

        if (json_last_error() !== JSON_ERROR_NONE ||
            !isset($responseArray['choices'][0]['message']['content'])) {
            throw new Exception('Error decoding JSON getAIPieceOfArticle or accessing content field');
        }

        return $responseArray['choices'][0]['message']['content'];

    } catch (Exception $e) {
        my_second_log('ERROR', $e->getMessage());
        return null;
    }
}


function calculateImagePositionsByTags($currentPart) {
    if (empty($currentPart)) {
        // If the current part is empty, return an empty array as there are no images
        return [];
    }

    $dom = new DOMDocument();
    @$dom->loadHTML(mb_convert_encoding($currentPart, 'HTML-ENTITIES', 'UTF-8'));

    $tagCount = 0;
    $imagePositions = [];
    foreach ($dom->getElementsByTagName('body')->item(0)->childNodes as $child) {
        if ($child->nodeType === XML_ELEMENT_NODE) {
            $tagCount++;
            if ($child->nodeName === 'img') {
                $imagePositions[] = ['tagCount' => $tagCount, 'tag' => $dom->saveHTML($child)];
            }
        }
    }

    return $imagePositions;
}

function reinsertImagesByTagCount($rewrittenPart, $imagePositions) {
    if (empty($rewrittenPart)) {
        // If the current part is empty, return an empty array as there are no images
        return [];
    }

    $dom = new DOMDocument();
    @$dom->loadHTML(mb_convert_encoding($rewrittenPart, 'HTML-ENTITIES', 'UTF-8'));

    $body = $dom->getElementsByTagName('body')->item(0);
    $tagCount = 0;
    foreach ($imagePositions as $image) {
        foreach ($body->childNodes as $child) {
            if ($child->nodeType === XML_ELEMENT_NODE) {
                $tagCount++;
                if ($tagCount == $image['tagCount']) {
                    $newImage = $dom->createDocumentFragment();
                    $newImage->appendXML($image['tag']);
                    $body->insertBefore($newImage, $child);
                    break;
                }
            }
        }
    }

    return $dom->saveHTML($body);
}




function getAiContent($contentAndTitle) {
    try {
        if (!isset($contentAndTitle['title'], $contentAndTitle['content'])) {
            throw new Exception('Invalid input array in getAiContent');
        }


        //first get shortened article, with all the information and precise data
        //then create subtitles


        $prompt = <<<EOD
            I am providing a scraped article from the web for rewriting. Your task is to read the article, and thoroughly rewrite it. The rewritten version should be as long as the original or longer, with an emphasis on expanding and enriching the content, maintaining accuracy and depth. Here are the specific instructions:

            - Expand and Enrich Content: Ensure the rewritten article matches or exceeds the word count of the original. Actively expand on points by providing additional context, detailed explanations, relevant examples, and supplementary information that enhances the depth and breadth of the article.

            - Maintain Essential Information: Preserve all key information and facts from the original article. Each point in the original should be clearly reflected and expanded upon in the rewritten version.

            - Exclude Unrelated Sections: Omit any 'related content', 'recommendations', 'suggested articles', or other parts not directly related to the main news story.

            - Handle Image Tags with Precision: Keep all existing image tags (<img src=...>) in their original positions within the text. Remove any sources or credits associated with these images. Do not create or infer new image tags.

            - Remove Video Descriptions: Exclude any descriptions or content related to videos in the original article.

            Here's the original content:

            {
            "content": "{$contentAndTitle['content']}"
            }

            Retain existing <img> tags in their exact positions, removing image credits. Also, remove links to other articles and sections like 'related content', 'recommendations', 'suggested articles'. 

            Your goal is to return a comprehensively rewritten version of the main content in HTML format. This version should be as extensive as or more detailed than the original, without any additional comments or explanations.
        EOD;





        $responseArray = sendToOpenAI($prompt);

        if (json_last_error() !== JSON_ERROR_NONE ||
            !isset($responseArray['choices'][0]['message']['content'])) {
            throw new Exception('Error decoding JSON getAiContent or accessing content field');
        }


        return $responseArray['choices'][0]['message']['content'];

    } catch (Exception $e) {
        my_second_log('ERROR', $e->getMessage());
        return null;
    }
}

function getAiContentOLDWORKING($contentAndTitle) {
    if (!isset($contentAndTitle['title'], $contentAndTitle['content'])) {
        my_second_log('ERROR', 'Invalid input array in getAiContent');
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
        my_second_log("ERROR", "Error decoding JSON getAiContent or accessing content field.");
        return null;
    }
}

