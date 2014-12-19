<?php
//Copyright (c) 2014, Carlos C
//All rights reserved.
//
//Redistribution and use in source and binary forms, with or without modification, are permitted provided that the following conditions are met:
//
//1. Redistributions of source code must retain the above copyright notice, this list of conditions and the following disclaimer.
//
//2. Redistributions in binary form must reproduce the above copyright notice, this list of conditions and the following disclaimer in the documentation and/or other materials provided with the distribution.
//
//3. Neither the name of the copyright holder nor the names of its contributors may be used to endorse or promote products derived from this software without specific prior written permission.
//
//THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT HOLDER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
//Copyright (c) 2014, Carlos C
//All rights reserved.
//
//Redistribution and use in source and binary forms, with or without modification, are permitted provided that the following conditions are met:
//
//1. Redistributions of source code must retain the above copyright notice, this list of conditions and the following disclaimer.
//
//2. Redistributions in binary form must reproduce the above copyright notice, this list of conditions and the following disclaimer in the documentation and/or other materials provided with the distribution.
//
//3. Neither the name of the copyright holder nor the names of its contributors may be used to endorse or promote products derived from this software without specific prior written permission.
//
//THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT HOLDER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
define("ST_AT", "@");
define("ST_BRACKET_CLOSE", "]");
define("ST_BRACKET_OPEN", "[");
define("ST_COLON", ":");
define("ST_COMMA", ",");
define("ST_CONCAT", ".");
define("ST_CURLY_CLOSE", "}");
define("ST_CURLY_OPEN", "{");
define("ST_DIVIDE", "/");
define("ST_DOLLAR", "$");
define("ST_EQUAL", "=");
define("ST_EXCLAMATION", "!");
define("ST_IS_GREATER", ">");
define("ST_IS_SMALLER", "<");
define("ST_MINUS", "-");
define("ST_MODULUS", "%");
define("ST_PARENTHESES_CLOSE", ")");
define("ST_PARENTHESES_OPEN", "(");
define("ST_PLUS", "+");
define("ST_QUESTION", "?");
define("ST_QUOTE", '"');
define("ST_REFERENCE", "&");
define("ST_SEMI_COLON", ";");
define("ST_TIMES", "*");
define("ST_BITWISE_OR", "|");
define("ST_BITWISE_XOR", "^");
if (!defined("T_POW")) {
	define("T_POW", "**");
}
if (!defined("T_POW_EQUAL")) {
	define("T_POW_EQUAL", "**=");
}
if (!defined("T_YIELD")) {
	define("T_YIELD", "yield");
}
if (!defined("T_FINALLY")) {
	define("T_FINALLY", "finally");
}
;
abstract class FormatterPass {
	protected $indent_char = "\t";
	protected $new_line = "\n";
	protected $indent = 0;
	protected $code = '';
	protected $ptr = 0;
	protected $tkns = [];
	protected $use_cache = false;
	protected $cache = [];
	protected $ignore_futile_tokens = [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT];

	protected function appendCode($code = "") {
		$this->code .= $code;
	}

	private function calculateCacheKey($direction, $ignore_list, $token) {
		return $direction . "\x2" . implode('', $ignore_list) . "\x2" . (is_array($token) ? implode("\x2", $token) : $token);
	}

	abstract public function candidate($source, $found_tokens);
	abstract public function format($source);

	protected function getToken($token) {
		if (isset($token[1])) {
			return $token;
		} else {
			return [$token, $token];
		}
	}

	protected function getCrlf($true = true) {
		return $true ? $this->new_line : "";
	}

	protected function getCrlfIndent() {
		return $this->getCrlf() . $this->getIndent();
	}

	protected function getIndent($increment = 0) {
		return str_repeat($this->indent_char, $this->indent + $increment);
	}

	protected function getSpace($true = true) {
		return $true ? " " : "";
	}

	protected function hasLn($text) {
		return (false !== strpos($text, $this->new_line));
	}

	protected function hasLnAfter() {
		$id = null;
		$text = null;
		list($id, $text) = $this->inspectToken();
		return T_WHITESPACE === $id && $this->hasLn($text);
	}

	protected function hasLnBefore() {
		$id = null;
		$text = null;
		list($id, $text) = $this->inspectToken(-1);
		return T_WHITESPACE === $id && $this->hasLn($text);
	}

	protected function hasLnLeftToken() {
		list($id, $text) = $this->getToken($this->leftToken());
		return $this->hasLn($text);
	}

	protected function hasLnRightToken() {
		list($id, $text) = $this->getToken($this->rightToken());
		return $this->hasLn($text);
	}

	protected function inspectToken($delta = 1) {
		if (!isset($this->tkns[$this->ptr + $delta])) {
			return [null, null];
		}
		return $this->getToken($this->tkns[$this->ptr + $delta]);
	}

	protected function leftToken($ignore_list = [], $idx = false) {
		$i = $this->leftTokenIdx($ignore_list);

		return $this->tkns[$i];
	}

	protected function leftTokenIdx($ignore_list = []) {
		$ignore_list = $this->resolveIgnoreList($ignore_list);

		$i = $this->walkLeft($this->tkns, $this->ptr, $ignore_list);

		return $i;
	}

	protected function leftTokenIs($token, $ignore_list = []) {
		return $this->tokenIs('left', $token, $ignore_list);
	}

	protected function leftTokenSubsetIsAtIdx($tkns, $idx, $token, $ignore_list = []) {
		$ignore_list = $this->resolveIgnoreList($ignore_list);

		$idx = $this->walkLeft($tkns, $idx, $ignore_list);

		return $this->resolveTokenMatch($tkns, $idx, $token);
	}

	protected function leftUsefulToken() {
		return $this->leftToken($this->ignore_futile_tokens);
	}

	protected function leftUsefulTokenIdx() {
		return $this->leftTokenIdx($this->ignore_futile_tokens);
	}

	protected function leftUsefulTokenIs($token) {
		return $this->leftTokenIs($token, $this->ignore_futile_tokens);
	}

	protected function printAndStopAt($tknids) {
		if (is_scalar($tknids)) {
			$tknids = [$tknids];
		}
		$tknids = array_flip($tknids);
		while (list($index, $token) = each($this->tkns)) {
			list($id, $text) = $this->getToken($token);
			$this->ptr = $index;
			$this->cache = [];
			if (isset($tknids[$id])) {
				return [$id, $text];
			}
			$this->appendCode($text);
		}
	}

	protected function printBlock($start, $end) {
		$count = 1;
		while (list($index, $token) = each($this->tkns)) {
			list($id, $text) = $this->getToken($token);
			$this->ptr = $index;
			$this->cache = [];
			$this->appendCode($text);

			if ($start == $id) {
				++$count;
			}
			if ($end == $id) {
				--$count;
			}
			if (0 == $count) {
				break;
			}
		}
	}

	protected function printCurlyBlock() {
		$count = 1;
		while (list($index, $token) = each($this->tkns)) {
			list($id, $text) = $this->getToken($token);
			$this->ptr = $index;
			$this->cache = [];
			$this->appendCode($text);

			if (ST_CURLY_OPEN == $id) {
				++$count;
			}
			if (T_CURLY_OPEN == $id) {
				++$count;
			}
			if (T_DOLLAR_OPEN_CURLY_BRACES == $id) {
				++$count;
			}
			if (ST_CURLY_CLOSE == $id) {
				--$count;
			}
			if (0 == $count) {
				break;
			}
		}
	}

	protected function printUntil($tknid) {
		while (list($index, $token) = each($this->tkns)) {
			list($id, $text) = $this->getToken($token);
			$this->ptr = $index;
			$this->cache = [];
			$this->appendCode($text);
			if ($tknid == $id) {
				break;
			}
		}
	}

	protected function printUntilAny($tknids) {
		$tknids = array_flip($tknids);
		$whitespace_new_line = false;
		if (isset($tknids[$this->new_line])) {
			$whitespace_new_line = true;
		}
		while (list($index, $token) = each($this->tkns)) {
			list($id, $text) = $this->getToken($token);
			$this->ptr = $index;
			$this->cache = [];
			$this->appendCode($text);
			if ($whitespace_new_line && T_WHITESPACE == $id && $this->hasLn($text)) {
				break;
			}
			if (isset($tknids[$id])) {
				break;
			}
		}
		return $id;
	}

	protected function printUntilTheEndOfString() {
		$this->printUntil(ST_QUOTE);
	}

	protected function render($tkns = null) {
		if (null == $tkns) {
			$tkns = $this->tkns;
		}

		$tkns = array_filter($tkns);
		$str = '';
		foreach ($tkns as $token) {
			list($id, $text) = $this->getToken($token);
			$str .= $text;
		}
		return $str;
	}

	protected function renderLight($tkns = null) {
		if (null == $tkns) {
			$tkns = $this->tkns;
		}
		$str = '';
		foreach ($tkns as $token) {
			$str .= $token[1];
		}
		return $str;
	}

	private function resolveIgnoreList($ignore_list = []) {
		if (empty($ignore_list)) {
			$ignore_list[T_WHITESPACE] = true;
		} else {
			$ignore_list = array_flip($ignore_list);
		}
		return $ignore_list;
	}

	private function resolveTokenMatch($tkns, $idx, $token) {
		if (!isset($tkns[$idx])) {
			return false;
		}

		$found_token = $tkns[$idx];
		if ($found_token === $token) {
			return true;
		} elseif (is_array($token) && isset($found_token[1]) && in_array($found_token[0], $token)) {
			return true;
		} elseif (is_array($token) && !isset($found_token[1]) && in_array($found_token, $token)) {
			return true;
		} elseif (isset($found_token[1]) && $found_token[0] == $token) {
			return true;
		}

		return false;
	}

	protected function rightToken($ignore_list = []) {
		$i = $this->rightTokenIdx($ignore_list);

		return $this->tkns[$i];
	}

	protected function rightTokenIdx($ignore_list = []) {
		$ignore_list = $this->resolveIgnoreList($ignore_list);

		$i = $this->walkRight($this->tkns, $this->ptr, $ignore_list);

		return $i;
	}

	protected function rightTokenIs($token, $ignore_list = []) {
		return $this->tokenIs('right', $token, $ignore_list);
	}

	protected function rightTokenSubsetIsAtIdx($tkns, $idx, $token, $ignore_list = []) {
		$ignore_list = $this->resolveIgnoreList($ignore_list);

		$idx = $this->walkRight($tkns, $idx, $ignore_list);

		return $this->resolveTokenMatch($tkns, $idx, $token);
	}

	protected function rightUsefulToken() {
		return $this->rightToken($this->ignore_futile_tokens);
	}

	// protected function right_useful_token_idx($idx = false) {
	// 	return $this->right_token_idx($this->ignore_futile_tokens);
	// }

	protected function rightUsefulTokenIs($token) {
		return $this->rightTokenIs($token, $this->ignore_futile_tokens);
	}

	protected function rtrimAndAppendCode($code = "") {
		$this->code = rtrim($this->code) . $code;
	}

	protected function scanAndReplace(&$tkns, &$ptr, $start, $end, $call, $look_for) {
		$look_for = array_flip($look_for);
		$placeholder = '<?php' . ' /*\x2 PHPOPEN \x3*/';
		$tmp = '';
		$tkn_count = 1;
		$found_potential_tokens = false;
		while (list($ptr, $token) = each($tkns)) {
			list($id, $text) = $this->getToken($token);
			if (isset($look_for[$id])) {
				$found_potential_tokens = true;
			}
			if ($start == $id) {
				++$tkn_count;
			}
			if ($end == $id) {
				--$tkn_count;
			}
			$tkns[$ptr] = null;
			if (0 == $tkn_count) {
				break;
			}
			$tmp .= $text;
		}
		if ($found_potential_tokens) {
			return $start . str_replace($placeholder, '', $this->{$call}($placeholder . $tmp)) . $end;
		}
		return $start . $tmp . $end;

	}

	protected function setIndent($increment) {
		$this->indent += $increment;
		if ($this->indent < 0) {
			$this->indent = 0;
		}
	}

	protected function siblings($tkns, $ptr) {
		$ignore_list = $this->resolveIgnoreList([T_WHITESPACE]);
		$left = $this->walkLeft($tkns, $ptr, $ignore_list);
		$right = $this->walkRight($tkns, $ptr, $ignore_list);
		return [$left, $right];
	}

	protected function substrCountTrailing($haystack, $needle) {
		return strlen(rtrim($haystack, " \t")) - strlen(rtrim($haystack, " \t" . $needle));
	}

	protected function tokenIs($direction, $token, $ignore_list = []) {
		if ('left' != $direction) {
			$direction = 'right';
		}
		if (!$this->use_cache) {
			return $this->{$direction . 'tokenSubsetIsAtIdx'}($this->tkns, $this->ptr, $token, $ignore_list);
		}

		$key = $this->calculateCacheKey($direction, $ignore_list, $token);
		if (isset($this->cache[$key])) {
			return $this->cache[$key];
		}

		$ret = $this->{$direction . 'tokenSubsetIsAtIdx'}($this->tkns, $this->ptr, $token, $ignore_list);
		$this->cache[$key] = $ret;

		return $ret;
	}

	protected function walkAndAccumulateUntil(&$tkns, $tknid) {
		$ret = '';
		while (list($index, $token) = each($tkns)) {
			list($id, $text) = $this->getToken($token);
			$this->ptr = $index;
			$ret .= $text;
			if ($tknid == $id) {
				break;
			}
		}
		return $ret;
	}

	private function walkLeft($tkns, $idx, $ignore_list) {
		$i = $idx;
		while (--$i >= 0 && isset($tkns[$i][1]) && isset($ignore_list[$tkns[$i][0]]));
		return $i;
	}

	private function walkRight($tkns, $idx, $ignore_list) {
		$i = $idx;
		$tkns_size = sizeof($tkns) - 1;
		while (++$i < $tkns_size && isset($tkns[$i][1]) && isset($ignore_list[$tkns[$i][0]]));
		return $i;
	}

	protected function walkUntil($tknid) {
		while (list($index, $token) = each($this->tkns)) {
			list($id, $text) = $this->getToken($token);
			$this->ptr = $index;
			if ($id == $tknid) {
				return [$id, $text];
			}
		}
	}
}
;
final class RefactorPass extends FormatterPass {
	private $from;
	private $to;
	public function __construct($from, $to) {
		$this->setFrom($from);
		$this->setTo($to);
	}
	private function setFrom($from) {
		$tkns = token_get_all('<?php ' . $from);
		array_shift($tkns);
		$tkns = array_map(function ($v) {
			return $this->getToken($v);
		}, $tkns);
		$this->from = $tkns;
		return $this;
	}
	private function getFrom() {
		return $this->from;
	}
	private function setTo($to) {
		$tkns = token_get_all('<?php ' . $to);
		array_shift($tkns);
		$tkns = array_map(function ($v) {
			return $this->getToken($v);
		}, $tkns);
		$this->to = $tkns;
		return $this;
	}
	private function getTo() {
		return $this->to;
	}
	public function candidate($source, $found_tokens) {
		return true;
	}
	public function format($source) {
		$from = $this->getFrom();
		$from_size = sizeof($from);
		$from_str = implode('', array_map(function ($v) {
			return $v[1];
		}, $from));
		$to = $this->getTo();
		$to_str = implode('', array_map(function ($v) {
			return $v[1];
		}, $to));

		$this->tkns = token_get_all($source);
		$this->code = '';
		while (list($index, $token) = each($this->tkns)) {
			list($id, $text) = $this->getToken($token);
			$this->ptr = $index;

			if ($id == $from[0][0]) {
				$match = true;
				$buffer = $text;
				for ($i = 1; $i < $from_size; ++$i) {
					list($index, $token) = each($this->tkns);
					$this->ptr = $index;
					list($id, $text) = $this->getToken($token);
					$buffer .= $text;
					if ('/*skipUntil' == substr($from[$i][1], 0, 11)) {
						$skip_call = $from[$i][1];
						$stop_text = strtolower(trim(str_replace('skipUntil:', '', substr($text, 2, -2))));
						++$i;
						while (list($index, $token) = each($this->tkns)) {
							$this->ptr = $index;
							list($id, $text) = $this->getToken($token);
							$buffer .= $text;
							if ($id == $from[$i][0]) {
								$tmp_i = $i;
								$tmp_ptr = $this->ptr;
								$s_match = true;
								for ($tmp_i; $tmp_i < $from_size; ++$tmp_i, ++$tmp_ptr) {
									if ($from[$tmp_i][0] != $this->tkns[$tmp_ptr][0]) {
										$s_match = false;
										break;
									}
								}
								if ($s_match) {
									break;
								} else {
									continue;
								}
							}
							if (strtolower($text) == $stop_text) {
								$match = false;
								break 2;
							}
						}
						continue;
					}
					if ($id != $from[$i][0]) {
						$match = false;
						break;
					}
				}
				if ($match) {
					if (strpos($to_str, '/*skip*/')) {
						$buffer = str_replace(explode($skip_call, $from_str), explode('/*skip*/', $to_str), $buffer);
					} else {
						$buffer = str_replace($from_str, $to_str, $buffer);
					}
				}

				$this->appendCode($buffer);
			} else {
				$this->appendCode($text);
			}
		}
		return $this->code;
	}
};

final class CodeFormatter {
	private $passes = [];
	private $debug = false;
	public function __construct($debug = false) {
		$this->debug = (bool) $debug;
	}
	public function addPass(FormatterPass $pass) {
		$this->passes[] = $pass;
	}

	public function formatCode($source = '') {
		gc_enable();
		$passes = array_map(
			function ($pass) {
				return clone $pass;
			},
			$this->passes
		);
		while (($pass = array_shift($passes))) {
			$source = $pass->format($source);
			gc_collect_cycles();
		}
		gc_disable();
		return $source;
	}
}
if (!isset($testEnv)) {
	$opts = getopt('ho:', ['from:', 'to:', 'help']);
	if (isset($opts['h']) || isset($opts['help'])) {
		echo 'Usage: ' . $argv[0] . ' [-ho] [--from=from --to=to] <target>', PHP_EOL;
		$options = [
			'--from=from, --to=to' => 'Search for "from" and replace with "to" - context aware search and replace',
			'-h, --help' => 'this help message',
			'-o=file' => 'output the formatted code to "file"',
		];
		$maxLen = max(array_map(function ($v) {
			return strlen($v);
		}, array_keys($options)));
		foreach ($options as $k => $v) {
			echo '  ', str_pad($k, $maxLen), '  ', $v, PHP_EOL;
		}
		echo PHP_EOL, 'If <target> is blank, it reads from stdin', PHP_EOL;
		die();
	}
	if (isset($opts['from']) && !isset($opts['to'])) {
		fwrite(STDERR, "Refactor must have --from and --to parameters" . PHP_EOL);
		exit(255);
	}

	$debug = false;

	$fmt = new CodeFormatter($debug);

	if (isset($opts['from']) && isset($opts['to'])) {
		$argv = array_values(
			array_filter($argv,
				function ($v) {
					$param_from = '--from';
					$param_to = '--to';
					return substr($v, 0, strlen($param_from)) !== $param_from && substr($v, 0, strlen($param_to)) !== $param_to;
				}
			)
		);
		$fmt->addPass(new RefactorPass($opts['from'], $opts['to']));
	}

	if (isset($opts['o'])) {
		unset($argv[1]);
		unset($argv[2]);
		$argv = array_values($argv);
		file_put_contents($opts['o'], $fmt->formatCode(file_get_contents($argv[1])));
	} elseif (isset($argv[1]) && is_file($argv[1])) {
		echo $fmt->formatCode(file_get_contents($argv[1]));
	} elseif (isset($argv[1]) && is_dir($argv[1])) {
		$dir = new RecursiveDirectoryIterator($argv[1]);
		$it = new RecursiveIteratorIterator($dir);
		$files = new RegexIterator($it, '/^.+\.php$/i', RecursiveRegexIterator::GET_MATCH);
		foreach ($files as $file) {
			$file = $file[0];
			echo $file;
			$orig_code = file_get_contents($file);
			$new_code = $fmt->formatCode($orig_code);
			if ($orig_code != $new_code) {
				file_put_contents($file . '-tmp', $new_code);
				rename($file, $file . '~');
				rename($file . '-tmp', $file);
			}
			echo PHP_EOL;
		}
	} else {
		echo $fmt->formatCode(file_get_contents('php://stdin'));
	}
}
