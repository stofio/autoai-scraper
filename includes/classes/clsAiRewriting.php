<?php
//this class calls openAI 
//with prompts for various parts like title and excerpt, text piece, piece with image, table

class OpenAIRewriting {

    private $openAIApiKey;
    private $openAIApiUrl = 'https://api.openai.com/v1/chat/completions'; // Static value, can stay outside constructor
    private $openAiModel;
    private $language;

	private $promptTemplates;

    public function __construct() {
        $this->openAIApiKey = get_option('open_ai_key_option');
        $this->openAiModel = get_option('open_ai_model', 'gpt-3.5-turbo');
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
					Rewrite this content piece in {$this->language}:
					Article title: '{title}',
					Content piece: '{content}',
					User Instructions: '{userPrompt}'. Return html elements.
				EOT
			],
			'articlePieceWithImage' => [
				'template' => <<<EOT
					Rewrite this content piece in {$this->language}. [IMG_PLACEHOLDER] represents an image tag <img/>.:
					Article title: '{title}',
					Content piece: '{content}',
					And suppose that before and after this content there may be other content of the entire article
					Rewriting instructions: '{userPrompt}'. 
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

	public function getRewrittenArticlePiece($contentPart, $title, $userPrompt) {
	    try {
	        if (empty($contentPart) || empty($title)) {
	            throw new Exception('Invalid input array in getRewrittenArticlePiece');
	        }

	        $dynamicParts = [
                'title' => $title,
                'content' => $contentPart,
                'userPrompt' => $userPrompt
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

	public function getRewrittenArticlePieceWithImage($contentPart, $title, $userPrompt) {
	    try {
	        if (empty($contentPart) || empty($title)) {
	            throw new Exception('Invalid input array in getRewrittenArticlePieceWithImage');
	        }

	        $dynamicParts = [
                'title' => $title,
                'content' => $contentPart,
                'userPrompt' => $userPrompt
            ];

	        $articlePrompt = $this->prepareDynamicPrompt('articlePieceWithImage', $dynamicParts);

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

    
}

?>