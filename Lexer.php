<?php
/*
    Token => type, value, $line, $cpos
*/

class Lexer {
	private $source, $cpos, $cposition, $line, $char, $keywords;
	const Undefined = -1;
	const TokenType = [
		'Keyword_if' => 1, 'Keyword_else' => 2, 'Keyword_print' => 3, 'Keyword_putc' => 4, 'Keyword_while' => 5,
		'Op_add' => 6, 'Op_and' => 7, 'Op_assign' => 8, 'Op_divide' => 9, 'Op_equal' => 10, 'Op_greater' => 11,
		'Op_greaterequal' => 12, 'Op_less' => 13, 'Op_lessequal' => 14, 'Op_mod' => 15, 'Op_multiply' => 16, 'Op_not' => 17,
		'Op_notequal' => 18, 'Op_or' => 19, 'Op_subtract' => 20,
		'Integer' => 21, 'String' => 22, 'Identifier' => 23,
		'Semicolon' => 24, 'Comma' => 25,
		'LeftBrace' => 26, 'RightBrace' => 27,
		'LeftParen' => 28, 'RightParen' => 29,
		'End_of_input' => 99
	];
    public function __construct($source) {
        $this->source = $source;
        $this->cpos = 0;        // position in line
        $this->cposition = 0;   // position in source
        $this->line = 1;
        $this->char = ' ';
        $this->keywords = [
            'if' => Lexer::TokenType['Keyword_if'],
            'else' => Lexer::TokenType['Keyword_else'],
            'print' => Lexer::TokenType['Keyword_print'],
            'putc' => Lexer::TokenType['Keyword_putc'],
            'while' => Lexer::TokenType['Keyword_while']
        ];
    }
    private function getNextChar() {
        $this->cpos++;
        $this->cposition++;
        
        if ($this->cposition >= strlen($this->source)) {
            $this->char = Lexer::Undefined;
            return $this->char;
        }
        $this->char = substr($this->source, $this->cposition, 1);
        if ($this->char === "\n") {
            $this->line++;
            $this->cpos = 0;
        }
		
        return $this->char;
    }
    private function error($line, $cpos, $message) {
        if ($line > 0 && $cpos > 0) {
            echo $message . ' in line ' . $line . ', pos ' . $cpos . '\n';
        } else {
            echo $message;
        }
        exit();
    }
    private function follow($expect, $ifyes, $ifno, $line, $cpos) {
        if ($this->getNextChar() === $expect) {
            $this->getNextChar();
            return [ 'type' => $ifyes, 'value' => '', 'line' => $line, 'cpos' => $cpos ];
        }
        if ($ifno === Lexer::TokenType['End_of_input']) {
            $this->error($line, $cpos, 'follow: unrecognized character = (' . substr($this->char, 0, 1) . ') "'. $this->char . '"');
        }
        return [ 'type' => $ifno, 'value' => '', 'line' => $line, 'cpos' => $cpos ];
    }
    private function div_or_comment($line, $cpos) {
        if ($this->getNextChar() !== '*') {
            return [ 'type' => Lexer::TokenType['Op_divide'], 'value' => '/', 'line' => $line, 'cpos' => $cpos ];
        }
        $this->getNextChar();
        while (true) { 
            if ($this->char === Lexer::Undefined) {
                $this->error($line, $cpos, 'EOF in comment');
            } else if ($this->char === '*') {
                if ($this->getNextChar() === '/') {
                    $this->getNextChar();
                    return $this->getToken();
                }
            } else {
                $this->getNextChar();
            }
        }
    }
    private function char_lit($line, $cpos) {
        $c = $this->getNextChar(); // skip opening quote
        $n = $c.charCodeAt(0);
        if ($c === "\'") {
            $this->error(line, $cpos, 'empty character constant');
        } else if ($c === "\\") {
            $c = $this->getNextChar();
            if ($c == 'n') {
                $n = 10;
            } else if ($c === "\\") {
                $n = 92;
            } else {
                $this->error($line, $cpos, 'unknown escape sequence \\' . c);
            }
        }
        if ($this->getNextChar() !== "\'") {
            $this->error($line, $cpos, 'multi-character constant');
        }
        $this->getNextChar();
        return [ 'type' => Lexer::TokenType['Integer'], 'value' => $n, 'line' => $line, 'cpos' => $cpos ];
    }
    private function String_lit($start, $line, $cpos) {
        $value = '';
        while ($this->getNextChar() !== $start) {;
            if ($this->char === Lexer::Undefined) {
                $this->error($line, $cpos, 'EOF while scanning String literal');
            }
            if ($this->char === "\n") {
                $this->error($line, $cpos, 'EOL while scanning String literal');
            }
            $value .= $this->char;
        }
        $this->getNextChar();
        return [ 'type' => Lexer::TokenType['String'], 'value' => $value, 'line' => $line, 'cpos' => $cpos ];
    }
    private function identifier_or_integer($line, $cpos) {
        $is_number = true;
        $text = '';
 
        while (preg_match('/\w/', $this->char) || $this->char === '_') {
            $text .= $this->char;
            if (!preg_match('/\d/', $this->char)) {
                $is_number = false;
            }
            $this->getNextChar();
        }
        if ($text === '') {
            $this->error($line, $cpos, 'identifer_or_integer: unrecopgnized character: (' . substr($this->char, 0, 1) . ') "' . $this->char . '"');
        }
 
        if (preg_match('/\d/', substr($text, 0, 1))) {
            if (!$is_number) {
                $this->error($line, $cpos, 'invaslid number => ' . $text);
            }
            return [ 'type' => Lexer::TokenType['Integer'], 'value' => $text, 'line' => $line, 'cpos' => $cpos ];
        }
 
        if (array_key_exists($text, $this->keywords)) {
            return [ 'type' => $this->keywords[$text], 'value' => '', 'line' => $line, 'cpos' => $cpos ];
        }
        return [ 'type' => Lexer::TokenType['Identifier'], 'value' => $text, 'line' => $line, 'cpos' => $cpos ];
    }
    private function getToken() {
        // Ignore whitespaces
        while (preg_match('/\s/', $this->char)) { $this->getNextChar(); }
        $line = $this->line; $cpos = $this->cpos;
        switch ($this->char) {
            case Lexer::Undefined: return [ 'type' => Lexer::TokenType['End_of_input'], 'value' => '', 'line' => $this->line, 'cpos' => $this->cpos ];
            case '/':       return $this->div_or_comment($line, $cpos);
            case "\'":      return $this->char_lit($line, $cpos);
            case "\"":      return $this->String_lit($this->char, $line, $cpos);

            case '<':       return $this->follow('=', Lexer::TokenType['Op_lessequal'], Lexer::TokenType['Op_less'], $line, $cpos);
            case '>':       return $this->follow('=', Lexer::TokenType['Op_greaterequal'], Lexer::TokenType['Op_greater'], $line, $cpos);
            case '=':       return $this->follow('=', Lexer::TokenType['Op_equal'], Lexer::TokenType['Op_assign'], $line, $cpos);
            case '!':       return $this->follow('=', Lexer::TokenType['Op_notequal'], Lexer::TokenType['Op_not'], $line, $cpos);
            case '&':       return $this->follow('&', Lexer::TokenType['Op_and'], Lexer::TokenType['End_of_input'], $line, $cpos);
            case '|':       return $this->follow('|', Lexer::TokenType['Op_or'], Lexer::TokenType['End_of_input'], $line, $cpos);
			
            case '{':       $this->getNextChar(); return [ 'type' => Lexer::TokenType['LeftBrace'], 'value' => '{', 'line' => $line, 'cpos' => $cpos ];
            case '}':       $this->getNextChar(); return [ 'type' => Lexer::TokenType['RightBrace'], 'value' => '}', 'line' => $line, 'cpos' => $cpos ];
            case '(':       $this->getNextChar(); return [ 'type' => Lexer::TokenType['LeftParen'], 'value' => '(', 'line' => $line, 'cpos' => $cpos ];
            case ')':       $this->getNextChar(); return [ 'type' => Lexer::TokenType['RightParen'], 'value' => ')', 'line' => $line, 'cpos' => $cpos ];
            case '+':       $this->getNextChar(); return [ 'type' => Lexer::TokenType['Op_add'], 'value' => '+', 'line' => $line, 'cpos' => $cpos ];
            case '-':       $this->getNextChar(); return [ 'type' => Lexer::TokenType['Op_subtract'], 'value' => '-', 'line' => $line, 'cpos' => $cpos ];
            case '*':       $this->getNextChar(); return [ 'type' => Lexer::TokenType['Op_multiply'], 'value' => '*', 'line' => $line, 'cpos' => $cpos ];
            case '%':       $this->getNextChar(); return [ 'type' => Lexer::TokenType['Op_mod'], 'value' => '%', 'line' => $line, 'cpos' => $cpos ];
            case ';':       $this->getNextChar(); return [ 'type' => Lexer::TokenType['Semicolon'], 'value' => ';', 'line' => $line, 'cpos' => $cpos ];
            case ',':       $this->getNextChar(); return [ 'type' => Lexer::TokenType['Comma'], 'value' => ',', 'line' => $line, 'cpos' => $cpos ];

            default:        return $this->identifier_or_integer($line, $cpos);
        }
    }
    private function getTokenType($value) {
        return array_search($value, Lexer::TokenType);
    }
    private function printToken($t) {
        $result = substr('     ' . $t['line'], 0, 16);
        $result .= substr('       ' . $t['cpos'], 0, 16);
        $result .= substr(' ' . $this->getTokenType($t['type']) . '           ', 0, 16);
        switch ($t['type']) {
            case Lexer::TokenType['Integer']:
                $result .= '  ' . $t['value'];
                break;
            case Lexer::TokenType['Identifier']:
                $result .= ' ' . $t['value'];
                break;
            case Lexer::TokenType['String']:
                $result .= ' \''. $t['value'] . '\'';
                break;
        }
        echo $result."\n";
    }
    public function printTokens() {
        $t = '';
        while (($t = $this->getToken())['type'] !== Lexer::TokenType['End_of_input']) {
            $this->printToken($t);
        }
        $this->printToken($t);
    }
}

$file = file_get_contents($argv[1]);
$l = new Lexer(' ' . $file);
$l->printTokens();
