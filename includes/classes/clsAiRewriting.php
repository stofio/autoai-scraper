<?php
//this class calls openAI 
//with prompts for various parts like title and excerpt, text piece, piece with image, table

// Prevent direct access to this file
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

class OpenAIRewriting {

    private $openAIApiKey;
    private $openAIApiUrl = 'https://api.openai.com/v1/chat/completions'; // Static value, can stay outside constructor
    private $openAiModel;
    private $language;



	private $promptTemplates;

    public function __construct($aiModel) {
        $this->openAIApiKey = get_option('open_ai_key_option');
        $this->openAiModel = $aiModel;
        $this->language = get_option('autoai_output_language', 'English');

		$this->promptTemplates = [
			'titleAndExcerpt' => [
				'template' => <<<EOT
					Rewrite this article's title and make an excerpt in {$this->language}, and return in JSON format:
					So this is the original article:
					{
						"title": "{title}",
						"content": "{content}"
					}
					Other instructions: {userPrompt}.
					So, I need you to return ONLY json for title and excerpt in {$this->language}, nothing else before or after, just like this example:
					{
						"title": "",
						"excerpt": ""
					}
				EOT
			],
			'articlePiece' => [
				'template' => <<<EOT
					Rewrite this content piece in {$this->language}.
					{userPromptBefore}
					Content to rewrite:
					Article title: '{title}',
					Content piece: '{content}',
					Rewriting Instructions: {userPromptAfter}. 
					Return only the content rewritten in html elements without title.
				EOT
			],
			'articlePieceWithImage' => [
				'template' => <<<EOT
					Rewrite this content piece in {$this->language}. 
					{userPromptBefore}
					[IMG_PLACEHOLDER] represents an image tag <img/>. 
					Content to rewrite:
					Article title: '{title}',
					Content piece: '{content}',
					Rewriting instructions: {userPromptAfter}. 
					Return only the content rewritten in html elements without title, reinsert [IMG_PLACEHOLDER] in the content.
				EOT
			],
			'table' => [
				'template' => <<<EOT
					Rewrite this table in {$this->language}. Return only the table in html format, change the text if possible:
					Article title: '{title}',
					Table: '{table}',
					User Instructions: '{userPrompt}'. Return only an html table.
				EOT
			]
		];
    }

    //args: type=prompt, dynamicParts=array
	private function prepareDynamicPrompt($type, $dynamicParts) {
		
	    if (!isset($this->promptTemplates[$type])) {
	        throw new Exception("Prompt type {$type} not defined.");
	    }

	    $prompt = $this->promptTemplates[$type]['template'];

	    foreach ($dynamicParts as $key => $value) {
	        $prompt = str_replace('{' . $key . '}', $value, $prompt);
	    }

	    return $prompt;
	}

	private function sendToOpenAI($prompt) {
	    try {
	        $ch = curl_init($this->openAIApiUrl);
	        curl_setopt_array($ch, [
	            CURLOPT_RETURNTRANSFER => true,
	            CURLOPT_POST => true,
	            CURLOPT_HTTPHEADER => [
	                'Content-Type: application/json',
	                'Authorization: Bearer ' . $this->openAIApiKey
	            ],
	            CURLOPT_POSTFIELDS => json_encode([
	                'model' => $this->openAiModel,
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


	public function getRewrittenTitleAndExcerpt($contentAndTitle, $userPrompt) {
	    try {
			
	        if (!isset($contentAndTitle['title'], $contentAndTitle['content'])) {
	            throw new Exception('Invalid input array in getAiTitleAndExcerpt');
	        }			

	        $dynamicPartsTitleAndExcerpt = [
                'title' => $contentAndTitle['title'],
                'content' => $contentAndTitle['content'],
                'userPrompt' => $userPrompt
            ];

	        $prompt = $this->prepareDynamicPrompt('titleAndExcerpt', $dynamicPartsTitleAndExcerpt);
			
	        $titleAndExcerptResponseArray = $this->sendToOpenAI($prompt);

	        if (json_last_error() !== JSON_ERROR_NONE ||
	            !isset($titleAndExcerptResponseArray['choices'][0]['message']['content'])) {
	            throw new Exception('Error decoding JSON in getAiTitleAndExcerpt or accessing content field');
	        }

	        $contentJsonString = $titleAndExcerptResponseArray['choices'][0]['message']['content'];
	        return json_decode($contentJsonString, true);
	    } catch (Exception $e) {
	        my_second_log('ERROR', $e->getMessage());
	        return null;
	    }
	}

	public function getRewrittenArticlePiece($contentPart, $title, $userPromptBefore, $userPromptAfter) {
	    try {
	        if (empty($contentPart) || empty($title)) {
	            throw new Exception('Invalid input array in getRewrittenArticlePiece');
	        }

	        $dynamicParts = [
                'title' => $title,
                'content' => $contentPart,
                'userPromptBefore' => $userPromptBefore,
                'userPromptAfter' => $userPromptAfter,
            ];

	        $articlePrompt = $this->prepareDynamicPrompt('articlePiece', $dynamicParts);

			
	        $articlePieceResponseArray = $this->sendToOpenAI($articlePrompt);

			
	        if (json_last_error() !== JSON_ERROR_NONE ||
			!isset($articlePieceResponseArray['choices'][0]['message']['content'])) {
	            throw new Exception('Error decoding JSON sendPromptPieceArticle or accessing content field');
	        }

	        return $articlePieceResponseArray['choices'][0]['message']['content'];

	    } catch (Exception $e) {
	        my_second_log('ERROR', $e->getMessage());
	        return null;
	    }
	}

	public function getRewrittenArticlePieceWithImage($contentPart, $title, $userPromptBefore, $userPromptAfter) {
	    try {
	        if (empty($contentPart) || empty($title)) {
	            throw new Exception('Invalid input array in getRewrittenArticlePieceWithImage');
	        }

	        $dynamicParts = [
				'title' => $title,
                'content' => $contentPart,
                'userPromptBefore' => $userPromptBefore,
                'userPromptAfter' => $userPromptAfter,
            ];
			
	        $articlePrompt = $this->prepareDynamicPrompt('articlePieceWithImage', $dynamicParts);

			$maxRetries = 5;
			for($i = 0; $i < $maxRetries; $i++) {
				
				$articlePieceResponseArray = $this->sendToOpenAI($articlePrompt);
				$validated = $this->validateImagePlaceholder($articlePieceResponseArray['choices'][0]['message']['content']);
				
				if($validated != false) {
					$articlePieceResponseArray['choices'][0]['message']['content'] = $validated;
					break; 
				}
			}

	        return $articlePieceResponseArray['choices'][0]['message']['content'];

	    } catch (Exception $e) {
	        my_second_log('ERROR', $e->getMessage());
	        return null;
	    }
	}


    public function rewriteTable($table, $title, $userPrompt) {
		try {
	        if (empty($table) || empty($title)) {
	            throw new Exception('Invalid input array in getRewrittenArticlePiece');
	        }

	        $dynamicParts = [
                'title' => $title,
                'table' => $table,
                'userPrompt' => $userPrompt
            ];

	        $tablePrompt = $this->prepareDynamicPrompt('table', $dynamicParts);
			

	        $tableResponseArray = $this->sendToOpenAI($tablePrompt);

	        if (json_last_error() !== JSON_ERROR_NONE ||
	            !isset($tableResponseArray['choices'][0]['message']['content'])) {
	            throw new Exception('Error decoding JSON sendPromptPieceArticle or accessing content field');
	        }
			
	        return $tableResponseArray['choices'][0]['message']['content'];



	    } catch (Exception $e) {
	        my_second_log('ERROR', $e->getMessage());
	        return null;
	    }
    }

	//return false if placeholder not present, or return chunk if its wrong placed
	private function validateImagePlaceholder($chunk) {
		//my_log($chunk);
		// check if and image tag is present into $chunk and replace it
		$doc = new DOMDocument();
		$doc->loadHTML($chunk, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
		$images = $doc->getElementsByTagName('img');
		if($images->length > 0) {
			// Loop through images and replace with placeholder
			foreach($images as $image) {
				$placeholder = $doc->createTextNode('[IMG_PLACEHOLDER]');
				$image->parentNode->insertBefore($placeholder, $image); 
				$image->parentNode->removeChild($image);
			}
			
			$res = $doc->saveHTML();
			return $res;
		}
		
		// Check if [IMG_PLACEHOLDER] exists in the chunk
		if (strpos($chunk, '[IMG_PLACEHOLDER]') != false) {
			return $chunk;
		}

		// Either [IMG_PLACEHOLDER] doesn't exist or it's inside src="", return false
		return false;
	}

	public function getChunkFullPrompt() {
		return $this->promptTemplates['articlePiece']['template'];
	}


    
}

?>