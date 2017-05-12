<?php

namespace cebe\markdown;

use cebe\markdown\block\TableTrait;

// work around https://github.com/facebook/hhvm/issues/1120
defined('ENT_HTML401') || define('ENT_HTML401', 0);

/**
 * Markdown parser for the Jonglaria flavor, probably based on [markdown extra](http://michelf.ca/projects/php-markdown/extra/).
 *
 */
class JonglariaMarkdown extends Markdown
{
	// include block element parsing using traits
	use block\TableTrait;
	use block\FencedCodeTrait;

	use block\ListTrait;
	private $_depth = 0;


	// include inline element parsing using traits
	// TODO

	/**
	 * @var bool whether special attributes on code blocks should be applied on the `<pre>` element.
	 * The default behavior is to put them on the `<code>` element.
	 */
	public $codeAttributesOnPre = false;

	/**
	 * @inheritDoc
	 */
	protected $escapeCharacters = [
		// from Markdown
		'\\', // backslash
		'`', // backtick
		'*', // asterisk
		'_', // underscore
		'{', '}', // curly braces
		'[', ']', // square brackets
		'(', ')', // parentheses
		'#', // hash mark
		'+', // plus sign
		'-', // minus sign (hyphen)
		'.', // dot
		'!', // exclamation mark
		'<', '>',
		// added by MarkdownExtra
		':', // colon
		'|', // pipe
	];

	/*	private $_specialAttributesRegex = '(\{(([#\.][A-z0-9-_]+\s*)+)\}){0,3}';*/
	private $_specialAttributesRegex = '\{(([#\.][A-z0-9-_]+\s*)+)\}(\{(([#\.][A-z0-9-_]+\s*)+)\})?$';
	private $_pAttribute;
	/*private $_specialAttributesRegex = '\{(([#\.][A-z0-9-_]+\s*){0,3})\}'; */

	// TODO allow HTML intended 3 spaces

	// TODO add markdown inside HTML blocks

	// TODO implement definition lists

	// TODO implement footnotes

	// TODO implement Abbreviations


	// block parsing

	protected function identifyReference($line)
	{
		error_log("identifyReference\n",3,"log.txt");
		return ($line[0] === ' ' || $line[0] === '[') && preg_match('/^ {0,3}\[(.+?)\]:\s*([^\s]+?)(?:\s+[\'"](.+?)[\'"])?\s*('.$this->_specialAttributesRegex.')?\s*$/', $line);
	}


	/**
	 * Consume link references
	 */
	protected function consumeReference($lines, $current)
	{
		error_log("consumeReference\n",3,"log.txt");
		while (isset($lines[$current]) && preg_match('/^ {0,3}\[(.+?)\]:\s*(.+?)(?:\s+[\(\'"](.+?)[\)\'"])?\s*('.$this->_specialAttributesRegex.')?\s*$/', $lines[$current], $matches)) {
			$label = strtolower($matches[1]);

			$this->references[$label] = [
				'url' => $this->replaceEscape($matches[2]),
			];
			if (isset($matches[3])) {
				$this->references[$label]['title'] = $matches[3]."test";
			} else {
				// title may be on the next line
				if (isset($lines[$current + 1]) && preg_match('/^\s+[\(\'"](.+?)[\)\'"]\s*$/', $lines[$current + 1], $matches)) {
					$this->references[$label]['title'] = $matches[1];
					$current++;
				}
			}
			if (isset($matches[5])) {
				$this->references[$label]['attributes'] = $matches[5];
			}
			$current++;
		}
		return [false, --$current];
	}

	/**
	 * Consume lines for a fenced code block
	 */
	protected function consumeFencedCode($lines, $current)
	{
		error_log("consumeFencedCode",4);
		// consume until ```
		$block = [
			'code',
		];
		$line = rtrim($lines[$current]);
		if (($pos = strrpos($line, '`')) === false) {
			$pos = strrpos($line, '~');
		}
		$fence = substr($line, 0, $pos + 1);
		$block['attributes'] = substr($line, $pos);
		$content = [];
		for($i = $current + 1, $count = count($lines); $i < $count; $i++) {
			if (rtrim($line = $lines[$i]) !== $fence) {
				$content[] = $line;
			} else {
				break;
			}
		}
		$block['content'] = implode("\n", $content);
		return [$block, $i];
	}

	protected function renderCode($block)
	{
		$attributes = $this->renderAttributes($block);
		return ($this->codeAttributesOnPre ? "<pre$attributes><code>" : "<pre><code$attributes>")
			. htmlspecialchars($block['content'] . "\n", ENT_NOQUOTES | ENT_SUBSTITUTE, 'UTF-8')
			. "</code></pre>\n";
	}

	/**
	 * Renders a headline
	 */
	protected function renderHeadline($block)
	{
		foreach($block['content'] as $i => $element) {
			if ($element[0] === 'specialAttributes') {
				unset($block['content'][$i]);
				$block['attributes'] = $element[1];
			}
		}
		$tag = 'h' . $block['level'];
		$attributes = $this->renderAttributes($block);
		return "<$tag$attributes>" . rtrim($this->renderAbsy($block['content']), "# \t") . "</$tag>\n";
	}

	protected function renderAttributes($block)
	{
		$html = [];
		if (isset($block['attributes'])) {
			$attributes = preg_split('/\s+/', $block['attributes'], -1, PREG_SPLIT_NO_EMPTY);
			foreach($attributes as $attribute) {
				if ($attribute[0] === '#') {
					$html['id'] = substr($attribute, 1);
				} else {
					$html['class'][] = substr($attribute, 1);
				}
			}
		}
		$result = '';
		foreach($html as $attr => $value) {
			if (is_array($value)) {
				$value = trim(implode(' ', $value));
			}
			if (!empty($value)) {
				$result .= " $attr=\"$value\"";
			}
		}
		return $result;
	}


	// inline parsing


	/**
	 * @marker {
	 */
	protected function parseSpecialAttributes($text)
	{
		error_log("parseSpecialAttributes\n",3,"log.txt");
		if (preg_match("~$this->_specialAttributesRegex~", $text, $matches)) {
			error_log("parseSpecialAttributes matches[1]".$matches[1]."\n",3,"log.txt");
			error_log("parseSpecialAttributes matches[2]".$matches[2]."\n",3,"log.txt");
			if(isset($matches[3]))
				error_log("parseSpecialAttributes matches[3]".$matches[3]."\n",3,"log.txt");
			if(isset($matches[4]))
			{
				error_log("parseSpecialAttributes matches[4]".$matches[4].strlen($matches[0])."\n",3,"log.txt");
				$this->_pAttribute=$matches[4];
			}
			else
			{
				error_log("parseSpecialAttributes matches[4]=\"\"".strlen($matches[0])."\n",3,"log.txt");
				$this->_pAttribute="";
			}
			return [['specialAttributes', $matches[1]], strlen($matches[0])];
		}
		return [['text', '{'], 1];
	}

	protected function renderSpecialAttributes($block)
	{
		error_log("renderSpecialAttributes block[1]:".$block[1]."\n",3,"log.txt");
		return '{' . $block[1] . '}';
	}

	protected function parseInline($text)
	{
		$elements = parent::parseInline($text);
		// merge special attribute elements to links and images as they are not part of the final absy later
		$relatedElement = null;
		foreach($elements as $i => $element) {
			if ($element[0] === 'link' || $element[0] === 'image') {
				$relatedElement = $i;
			} elseif ($element[0] === 'specialAttributes') {
				if ($relatedElement !== null) {
					$elements[$relatedElement]['attributes'] = $element[1];
					unset($elements[$i]);
				}
				$relatedElement = null;
			} else {
				$relatedElement = null;
			}
		}
		return $elements;
	}

	protected function renderLink($block)
	{
		if (isset($block['refkey'])) {
			if (($ref = $this->lookupReference($block['refkey'])) !== false) {
				$block = array_merge($block, $ref);
			} else {
				return $block['orig'];
			}
		}
		$attributes = $this->renderAttributes($block);
		return '<a href="' . htmlspecialchars($block['url'], ENT_COMPAT | ENT_HTML401, 'UTF-8') . '"'
			. (empty($block['title']) ? '' : ' title="' . htmlspecialchars($block['title'], ENT_COMPAT | ENT_HTML401 | ENT_SUBSTITUTE, 'UTF-8') . '"')
			. $attributes . '>' . $this->renderAbsy($block['text']) . '</a>';
	}

	protected function renderImage($block)
	{
		if (isset($block['refkey'])) {
			if (($ref = $this->lookupReference($block['refkey'])) !== false) {
				$block = array_merge($block, $ref);
			} else {
				return $block['orig'];
			}
		}
		$attributes = $this->renderAttributes($block);
		return '<img src="' . htmlspecialchars($block['url'], ENT_COMPAT | ENT_HTML401, 'UTF-8') . '"'
			. ' alt="' . htmlspecialchars($block['text'], ENT_COMPAT | ENT_HTML401 | ENT_SUBSTITUTE, 'UTF-8') . '"'
			. (empty($block['title']) ? '' : ' title="' . htmlspecialchars($block['title'], ENT_COMPAT | ENT_HTML401 | ENT_SUBSTITUTE, 'UTF-8') . '"')
			. $attributes . ($this->html5 ? '>' : ' />');
	}

	protected function renderParagraph($block)
	{
		error_log("renderParagraph\n",3,"log.txt");
		if(empty($block['pAttribute']))
			return '<p>' . $this->renderAbsy($block['content']) . "</p>\n";
		else
		{
			if($block['pAttribute'][0] == '#')
				return '<p id="'.substr($block['pAttribute'],1).'">' . $this->renderAbsy($block['content']) . "</p>\n";
			else
				return '<p class="'.substr($block['pAttribute'],1).'">' . $this->renderAbsy($block['content']) . "</p>\n";
		}

	}

	protected function parseBlock($lines, $current)
	{
		// identify block type for this line
		$blockType = $this->detectLineType($lines, $current);
		error_log("parseBlock ".$this->_pAttribute." ".$blockType.":".$lines[$current]."\n",3,"log.txt");

		// call consume method for the detected block type to consume further lines
		$block = $this->{'consume' . $blockType}($lines, $current);
		error_log("parseBlock after consume ".$this->_pAttribute." ".$blockType.":".$lines[$current]."\n",3,"log.txt");
		return $block;
	}

	protected function parseBlocks($lines)
	{
		if ($this->_depth >= $this->maximumNestingLevel) {
			// maximum depth is reached, do not parse input
			return [['text', implode("\n", $lines)]];
		}
		$this->_depth++;

		$blocks = [];

		// convert lines to blocks
		for ($i = 0, $count = count($lines); $i < $count; $i++) {
			$line = $lines[$i];
			if ($line !== '' && rtrim($line) !== '') { // skip empty lines
				// identify a blocks beginning and parse the content
				list($block, $i) = $this->parseBlock($lines, $i);
				if ($block !== false) {
					if(!empty($this->_pAttribute))
					{
						$block['pAttribute'] = $this->_pAttribute;
						error_log("parseBlocks block".$block['pAttribute']."\n",3,"log.txt");
					}
					$blocks[] = $block;
				}
			}
		}

		$this->_depth--;

		return $blocks;
	}


}
