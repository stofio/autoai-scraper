<?php

define('API_KEY', get_option('open_ai_key_option'));
define('API_ENDPOINT', 'https://api.openai.com/v1/chat/completions');


function generateContentWithAI($article) {
    $titleAndExcerpt = getAiTitleAndExcerpt($article);

    // Check if title and excerpt were successfully generated
    if ($titleAndExcerpt === 'error') {
        return 'error'; // Propagate the error
    }


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
                'model' => get_option('open_ai_model'),
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

        $lang = get_option('autoai_output_language');

        $prompt = <<<EOD
            Im giving you next a news article in the PC niche. 
            I need you to rewrite only the excerpt and title in {$lang}, the title should be very similar to the original, and I need you to return it in JSON format (only json), exactly like the example, without the "content". Remember to make a similar title as the original.
            So this is the original article:
            {
                "title": "{$contentAndTitle['title']}",
                "content": "{$contentAndTitle['content']}"
            }
            So, I need you to return ONLY json for title and excerpt in {$lang}, nothing else before or after, like this example:
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

            if ($currentWordCount + $wordCount > get_option('word_count_per_open_ai_request', '')) {
                // Check if last node was a heading
                if ($lastNode && in_array($lastNode->nodeName, ['h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'img'])) {
                    $detachedHeading = $lastNode->parentNode->removeChild($lastNode);
                }

                // Process the current part
                $imagePositions = calculateImagePositionsByTags($currentPart);
                $rewrittenPart = getAIPieceOfArticle($currentPart, $title);
                $finalContent = reinsertImagesByTagCount($rewrittenPart, $imagePositions);
                

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
            return;
        }

        $lang = get_option('autoai_output_language');

        $originalPrompt = <<<EOD
        Rewrite in {$lang} the provided article excerpt, preserving its original meaning, structure, and HTML formatting. 
        Expand the content's depth while omitting unrelated details. 
        Exclude references to videos, credits, unrelated sections and unrelated text that is not part of the article context. The goal is a longer, enriched version of the text, focused solely on the article's content. Remove any parts that are not part of the story of the article, like related links, readings, recommendations...

        Here is the article excerpt to rewrite:
        "content": "{$contentPart}"

        Some of the "content" (or all of the given) is out of context from the article '{$title}', exclude that part.
        Remember to exclude videos related text, credits, and unrelated sections like 'releated readings', 'editor recommendations' sections with its content, ecc.
        The output should be a detailed, HTML-formatted text in {$lang} language that mirrors the original's key points of the entire article, with longer pharagraphs.
        EOD;


        $savedPrompt = get_option('prompt_partial_text', '');

        if($savedPrompt === '') {
            $prompt = $originalPrompt;
        }
        else {
            $swapped = str_replace(['{$lang}', '{$contentPart}', '{$title}'], [$lang, $contentPart, $title], $savedPrompt);
            $prompt = stripslashes($swapped);
        }


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
    if ($currentPart == '') {
        return [];
    }

    $dom = new DOMDocument();
    @$dom->loadHTML(mb_convert_encoding($currentPart, 'HTML-ENTITIES', 'UTF-8'));

    $imagePositions = [];
    $tagCount = 0;

    // Function to recursively search for image tags and count p tags
    $findImages = function($node) use (&$findImages, &$imagePositions, &$tagCount) {
        if ($node->nodeType === XML_ELEMENT_NODE) {
            if ($node->nodeName === 'p') {
                $tagCount++;
            }
            if ($node->nodeName === 'img') {
                $imagePositions[] = ['tagCount' => $tagCount, 'tag' => $node->ownerDocument->saveHTML($node)];
            }
            foreach ($node->childNodes as $child) {
                $findImages($child);
            }
        }
    };

    // Start the recursive search from the body node
    $body = $dom->getElementsByTagName('body')->item(0);
    foreach ($body->childNodes as $child) {
        $findImages($child);
    }

    return $imagePositions;
}




function reinsertImagesByTagCount($rewrittenPart, $imagePositions) {
    if ($rewrittenPart == '') {
        return $rewrittenPart;
    }

    $dom = new DOMDocument();
    @$dom->loadHTML(mb_convert_encoding($rewrittenPart, 'HTML-ENTITIES', 'UTF-8'));

    $body = $dom->getElementsByTagName('body')->item(0);

    // Get all element nodes in the body
    $elementNodes = [];
    foreach ($body->childNodes as $child) {
        if ($child->nodeType === XML_ELEMENT_NODE) {
            $elementNodes[] = $child;
        }
    }

    $numElements = count($elementNodes);

    foreach ($imagePositions as $image) {
        $imgDom = new DOMDocument();
        @$imgDom->loadHTML($image['tag']);

        $importedNode = $dom->importNode($imgDom->documentElement, true);

        if ($image['tagCount'] < $numElements) {
            $body->insertBefore($importedNode, $elementNodes[$image['tagCount']]);
        } else {
            $body->appendChild($importedNode);
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



