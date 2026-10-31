<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Field\Rules;

use Closure;
use DOMDocument;
use Illuminate\Contracts\Validation\ValidationRule;
use LibXMLError;

/**
 * The value is the XML of a model file whose root is `definitions` in the
 * notation's namespace — what BpmnModel and DmnModel share.
 *
 * The XML is parsed safely: a DOCTYPE is refused before parsing (so neither
 * internal nor external entities exist), the network is off (LIBXML_NONET)
 * and entities are never substituted. The size is capped before anything
 * else is done.
 *
 * An empty value passes: whether the field is required is up to `required`.
 */
abstract class XmlModel implements ValidationRule
{
    /** The default size cap: 2 MB. */
    public const DEFAULT_MAX_BYTES = 2 * 1024 * 1024;

    /**
     * @param  int  $maxBytes  The largest file accepted, in bytes.
     */
    public function __construct(protected readonly int $maxBytes = self::DEFAULT_MAX_BYTES) {}

    /** The notation's name for the messages: BPMN, DMN. */
    abstract protected function notation(): string;

    /** Whether the root's namespace is the notation's model namespace. */
    abstract protected function acceptsNamespace(string $namespace): bool;

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value === null || $value === '') {
            return;
        }

        $notation = $this->notation();
        if (! is_string($value)) {
            $fail((string) __('Модель :notation должна быть строкой XML.', ['notation' => $notation]));

            return;
        }

        if (strlen($value) > $this->maxBytes) {
            $fail((string) __('Модель :notation слишком большая: не более :max КБ.', [
                'notation' => $notation,
                'max' => (string) max(1, intdiv($this->maxBytes, 1024)),
            ]));

            return;
        }

        if (preg_match('/<!DOCTYPE/i', $value) === 1) {
            $fail((string) __('Модель :notation не может содержать DOCTYPE.', ['notation' => $notation]));

            return;
        }

        $dom = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        libxml_clear_errors();
        try {
            $loaded = trim($value) !== '' && $dom->loadXML($value, LIBXML_NONET);
            $errors = libxml_get_errors();
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }

        if (! $loaded || self::hasFatal($errors)) {
            $error = $errors[0] ?? null;
            $fail((string) __('Модель :notation не читается как XML: :error', [
                'notation' => $notation,
                'error' => $error instanceof LibXMLError
                    ? trim($error->message).' ('.__('строка').' '.$error->line.')'
                    : __('пустой документ'),
            ]));

            return;
        }

        // A DOCTYPE written in a way the check above missed still ends here.
        if ($dom->doctype !== null) {
            $fail((string) __('Модель :notation не может содержать DOCTYPE.', ['notation' => $notation]));

            return;
        }

        $root = $dom->documentElement;
        if ($root === null || $root->localName !== 'definitions') {
            $fail((string) __('Корневой элемент модели :notation должен быть definitions, а не :root.', [
                'notation' => $notation,
                'root' => $root === null ? '—' : $root->localName,
            ]));

            return;
        }

        $namespace = (string) $root->namespaceURI;
        if (! $this->acceptsNamespace($namespace)) {
            $fail((string) __('Неверное пространство имён модели :notation: :namespace', [
                'notation' => $notation,
                'namespace' => $namespace === '' ? '—' : $namespace,
            ]));
        }
    }

    /**
     * @param  array<int, LibXMLError>  $errors
     */
    private static function hasFatal(array $errors): bool
    {
        foreach ($errors as $error) {
            if ($error->level >= LIBXML_ERR_ERROR) {
                return true;
            }
        }

        return false;
    }
}
