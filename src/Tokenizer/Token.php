<?php

/*
 * This file is part of PHP CS Fixer.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *     Dariusz Rumiński <dariusz.ruminski@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace PhpCsFixer\Tokenizer;

use PhpCsFixer\Utils;

/**
 * Representation of single token.
 * As a token prototype you should understand a single element generated by token_get_all.
 *
 * @author Dariusz Rumiński <dariusz.ruminski@gmail.com>
 */
class Token
{
    /**
     * Content of token prototype.
     *
     * @var string
     */
    private $content;

    /**
     * ID of token prototype, if available.
     *
     * @var int|null
     */
    private $id;

    /**
     * If token prototype is an array.
     *
     * @var bool
     */
    private $isArray;

    /**
     * Flag is token was changed.
     *
     * @var bool
     */
    private $changed = false;

    /**
     * @param string|array $token token prototype
     */
    public function __construct($token)
    {
        if (is_array($token)) {
            $this->isArray = true;
            $this->id = $token[0];
            $this->content = $token[1];
        } elseif (is_string($token)) {
            $this->isArray = false;
            $this->content = $token;
        } else {
            throw new \InvalidArgumentException(sprintf(
                'Cannot recognize input value as valid Token prototype, got "%s".',
                is_object($token) ? get_class($token) : gettype($token)
            ));
        }
    }

    /**
     * @return int[]
     */
    public static function getCastTokenKinds()
    {
        static $castTokens = [T_ARRAY_CAST, T_BOOL_CAST, T_DOUBLE_CAST, T_INT_CAST, T_OBJECT_CAST, T_STRING_CAST, T_UNSET_CAST];

        return $castTokens;
    }

    /**
     * Get classy tokens kinds: T_CLASS, T_INTERFACE and T_TRAIT.
     *
     * @return int[]
     */
    public static function getClassyTokenKinds()
    {
        static $classTokens = [T_CLASS, T_TRAIT, T_INTERFACE];

        return $classTokens;
    }

    /**
     * Clear token at given index.
     *
     * Clearing means override token by empty string.
     */
    public function clear()
    {
        $this->override('');
    }

    /**
     * Clear internal flag if token was changed.
     */
    public function clearChanged()
    {
        $this->changed = false;
    }

    /**
     * Check if token is equals to given one.
     *
     * If tokens are arrays, then only keys defined in parameter token are checked.
     *
     * @param Token|array|string $other         token or it's prototype
     * @param bool               $caseSensitive perform a case sensitive comparison
     *
     * @return bool
     */
    public function equals($other, $caseSensitive = true)
    {
        $otherPrototype = $other instanceof self ? $other->getPrototype() : $other;

        if ($this->isArray !== is_array($otherPrototype)) {
            return false;
        }

        if (!$this->isArray) {
            return $this->content === $otherPrototype;
        }

        if (array_key_exists(0, $otherPrototype) && $this->id !== $otherPrototype[0]) {
            return false;
        }

        if (array_key_exists(1, $otherPrototype)) {
            if ($caseSensitive) {
                if ($this->content !== $otherPrototype[1]) {
                    return false;
                }
            } elseif (0 !== strcasecmp($this->content, $otherPrototype[1])) {
                return false;
            }
        }

        // detect unknown keys
        unset($otherPrototype[0], $otherPrototype[1]);

        return empty($otherPrototype);
    }

    /**
     * Check if token is equals to one of given.
     *
     * @param array $others        array of tokens or token prototypes
     * @param bool  $caseSensitive perform a case sensitive comparison
     *
     * @return bool
     */
    public function equalsAny(array $others, $caseSensitive = true)
    {
        foreach ($others as $other) {
            if ($this->equals($other, $caseSensitive)) {
                return true;
            }
        }

        return false;
    }

    /**
     * A helper method used to find out whether or not a certain input token has to be case-sensitively matched.
     *
     * @param bool|array<int, bool> $caseSensitive global case sensitiveness or an array of booleans, whose keys should match
     *                                             the ones used in $others. If any is missing, the default case-sensitive
     *                                             comparison is used
     * @param int                   $key           the key of the token that has to be looked up
     *
     * @return bool
     */
    public static function isKeyCaseSensitive($caseSensitive, $key)
    {
        if (is_array($caseSensitive)) {
            return isset($caseSensitive[$key]) ? $caseSensitive[$key] : true;
        }

        return $caseSensitive;
    }

    /**
     * @return string|array token prototype
     */
    public function getPrototype()
    {
        if (!$this->isArray) {
            return $this->content;
        }

        return [
            $this->id,
            $this->content,
        ];
    }

    /**
     * Get token's content.
     *
     * It shall be used only for getting the content of token, not for checking it against excepted value.
     *
     * @return string
     */
    public function getContent()
    {
        return $this->content;
    }

    /**
     * Get token's id.
     *
     * It shall be used only for getting the internal id of token, not for checking it against excepted value.
     *
     * @return int|null
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * Get token's name.
     *
     * It shall be used only for getting the name of token, not for checking it against excepted value.
     *
     * @return null|string token name
     */
    public function getName()
    {
        if (null === $this->id) {
            return null;
        }

        if (CT::has($this->id)) {
            return CT::getName($this->id);
        }

        return token_name($this->id);
    }

    /**
     * Generate array containing all keywords that exists in PHP version in use.
     *
     * @return array<int, int>
     */
    public static function getKeywords()
    {
        static $keywords = null;

        if (null === $keywords) {
            $keywords = self::getTokenKindsForNames(['T_ABSTRACT', 'T_ARRAY', 'T_AS', 'T_BREAK', 'T_CALLABLE', 'T_CASE',
                'T_CATCH', 'T_CLASS', 'T_CLONE', 'T_CONST', 'T_CONTINUE', 'T_DECLARE', 'T_DEFAULT', 'T_DO',
                'T_ECHO', 'T_ELSE', 'T_ELSEIF', 'T_EMPTY', 'T_ENDDECLARE', 'T_ENDFOR', 'T_ENDFOREACH',
                'T_ENDIF', 'T_ENDSWITCH', 'T_ENDWHILE', 'T_EVAL', 'T_EXIT', 'T_EXTENDS', 'T_FINAL',
                'T_FINALLY', 'T_FOR', 'T_FOREACH', 'T_FUNCTION', 'T_GLOBAL', 'T_GOTO', 'T_HALT_COMPILER',
                'T_IF', 'T_IMPLEMENTS', 'T_INCLUDE', 'T_INCLUDE_ONCE', 'T_INSTANCEOF', 'T_INSTEADOF',
                'T_INTERFACE', 'T_ISSET', 'T_LIST', 'T_LOGICAL_AND', 'T_LOGICAL_OR', 'T_LOGICAL_XOR',
                'T_NAMESPACE', 'T_NEW', 'T_PRINT', 'T_PRIVATE', 'T_PROTECTED', 'T_PUBLIC', 'T_REQUIRE',
                'T_REQUIRE_ONCE', 'T_RETURN', 'T_STATIC', 'T_SWITCH', 'T_THROW', 'T_TRAIT', 'T_TRY',
                'T_UNSET', 'T_USE', 'T_VAR', 'T_WHILE', 'T_YIELD',
            ]) + [
                CT::T_ARRAY_TYPEHINT => CT::T_ARRAY_TYPEHINT,
                CT::T_CLASS_CONSTANT => CT::T_CLASS_CONSTANT,
                CT::T_CONST_IMPORT => CT::T_CONST_IMPORT,
                CT::T_FUNCTION_IMPORT => CT::T_FUNCTION_IMPORT,
                CT::T_NAMESPACE_OPERATOR => CT::T_NAMESPACE_OPERATOR,
                CT::T_USE_TRAIT => CT::T_USE_TRAIT,
                CT::T_USE_LAMBDA => CT::T_USE_LAMBDA,
            ];
        }

        return $keywords;
    }

    /**
     * Generate array containing all predefined constants that exists in PHP version in use.
     *
     * @see http://php.net/manual/en/language.constants.predefined.php
     *
     * @return array<int, int>
     */
    public static function getMagicConstants()
    {
        static $magicConstants = null;

        if (null === $magicConstants) {
            $magicConstants = self::getTokenKindsForNames(['T_CLASS_C', 'T_DIR', 'T_FILE', 'T_FUNC_C', 'T_LINE', 'T_METHOD_C', 'T_NS_C', 'T_TRAIT_C']);
        }

        return $magicConstants;
    }

    /**
     * Check if token prototype is an array.
     *
     * @return bool is array
     */
    public function isArray()
    {
        return $this->isArray;
    }

    /**
     * Check if token is one of type cast tokens.
     *
     * @return bool
     */
    public function isCast()
    {
        return $this->isGivenKind(self::getCastTokenKinds());
    }

    /**
     * Check if token was changed.
     *
     * @return bool
     */
    public function isChanged()
    {
        return $this->changed;
    }

    /**
     * Check if token is one of classy tokens: T_CLASS, T_INTERFACE or T_TRAIT.
     *
     * @return bool
     */
    public function isClassy()
    {
        return $this->isGivenKind(self::getClassyTokenKinds());
    }

    /**
     * Check if token is one of comment tokens: T_COMMENT or T_DOC_COMMENT.
     *
     * @return bool
     */
    public function isComment()
    {
        static $commentTokens = [T_COMMENT, T_DOC_COMMENT];

        return $this->isGivenKind($commentTokens);
    }

    /**
     * Check if token is empty, e.g. because of clearing.
     *
     * @return bool
     */
    public function isEmpty()
    {
        return null === $this->id && ('' === $this->content || null === $this->content);
    }

    /**
     * Check if token is one of given kind.
     *
     * @param int|int[] $possibleKind kind or array of kinds
     *
     * @return bool
     */
    public function isGivenKind($possibleKind)
    {
        return $this->isArray && (is_array($possibleKind) ? in_array($this->id, $possibleKind, true) : $this->id === $possibleKind);
    }

    /**
     * Check if token is a keyword.
     *
     * @return bool
     */
    public function isKeyword()
    {
        $keywords = static::getKeywords();

        return $this->isArray && isset($keywords[$this->id]);
    }

    /**
     * Check if token is a native PHP constant: true, false or null.
     *
     * @return bool
     */
    public function isNativeConstant()
    {
        static $nativeConstantStrings = ['true', 'false', 'null'];

        return $this->isArray && in_array(strtolower($this->content), $nativeConstantStrings, true);
    }

    /**
     * Returns if the token is of a Magic constants type.
     *
     * @see http://php.net/manual/en/language.constants.predefined.php
     *
     * @return bool
     */
    public function isMagicConstant()
    {
        $magicConstants = static::getMagicConstants();

        return $this->isArray && isset($magicConstants[$this->id]);
    }

    /**
     * Check if token is a whitespace.
     *
     * @param null|string $whitespaces whitespaces characters, default is " \t\n\r\0\x0B"
     *
     * @return bool
     */
    public function isWhitespace($whitespaces = " \t\n\r\0\x0B")
    {
        if (null === $whitespaces) {
            $whitespaces = " \t\n\r\0\x0B";
        }

        if ($this->isArray && !$this->isGivenKind(T_WHITESPACE)) {
            return false;
        }

        return '' === trim($this->content, $whitespaces);
    }

    /**
     * Override token.
     *
     * If called on Token inside Tokens collection please use `Tokens::overrideAt` instead.
     *
     * @param Token|array|string $other token prototype
     */
    public function override($other)
    {
        $prototype = $other instanceof self ? $other->getPrototype() : $other;

        if ($this->equals($prototype)) {
            return;
        }

        $this->changed = true;

        if (is_array($prototype)) {
            $this->isArray = true;
            $this->id = $prototype[0];
            $this->content = $prototype[1];

            return;
        }

        $this->isArray = false;
        $this->id = null;
        $this->content = $prototype;
    }

    /**
     * @param string $content
     */
    public function setContent($content)
    {
        // setting empty content is clearing the token
        if ('' === $content) {
            $this->clear();

            return;
        }

        if ($this->content === $content) {
            return;
        }

        $this->changed = true;
        $this->content = $content;
    }

    public function toArray()
    {
        return [
            'id' => $this->id,
            'name' => $this->getName(),
            'content' => $this->content,
            'isArray' => $this->isArray,
            'changed' => $this->changed,
        ];
    }

    /**
     * @param string[]|null $options JSON encode option
     *
     * @return string
     */
    public function toJson(array $options = null)
    {
        static $defaultOptions = null;

        if (null === $options) {
            if (null === $defaultOptions) {
                $defaultOptions = Utils::calculateBitmask(['JSON_PRETTY_PRINT', 'JSON_NUMERIC_CHECK']);
            }

            $options = $defaultOptions;
        } else {
            $options = Utils::calculateBitmask($options);
        }

        return json_encode($this->toArray(), $options);
    }

    /**
     * @param string[] $tokenNames
     *
     * @return array<int, int>
     */
    private static function getTokenKindsForNames(array $tokenNames)
    {
        $keywords = [];
        foreach ($tokenNames as $keywordName) {
            if (defined($keywordName)) {
                $keyword = constant($keywordName);
                $keywords[$keyword] = $keyword;
            }
        }

        return $keywords;
    }
}
