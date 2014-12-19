<?php
class MergeNamespaceWithOpenTag extends AdditionalPass {
	public function candidate($source, $found_tokens) {
		if (isset($found_tokens[T_NAMESPACE])) {
			return true;
		}

		return false;
	}
	public function format($source) {
		$this->tkns = token_get_all($source);
		$this->code = '';
		while (list($index, $token) = each($this->tkns)) {
			list($id, $text) = $this->getToken($token);
			$this->ptr = $index;
			switch ($id) {
				case T_NAMESPACE:
					if ($this->leftTokenIs(T_OPEN_TAG)) {
						$this->rtrimAndAppendCode($this->new_line . $text);
						break 2;
					}

				default:
					$this->appendCode($text);
					break;
			}
		}
		while (list($index, $token) = each($this->tkns)) {
			list($id, $text) = $this->getToken($token);
			$this->appendCode($text);
		}
		return $this->code;
	}
	/**
	 * @codeCoverageIgnore
	 */
	public function get_description() {
		return 'Ensure there is no more than one linebreak before namespace';
	}

	/**
	 * @codeCoverageIgnore
	 */
	public function get_example() {
		return <<<'EOT'
<?php

namespace A;
?>
to
<?php
namespace A;
?>
EOT;
	}
}
