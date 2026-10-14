<?php

namespace Goldnead\Directory\Scopes;

/** A filter with the two answers yes and no; the subclass says what they mean for the query. */
abstract class YesNoFilter extends DirectoryFilter
{
    protected $pinned = true;

    abstract protected static function label(): string;

    abstract protected function constrain($query, bool $yes): void;

    public static function title()
    {
        return static::label();
    }

    public function fieldItems()
    {
        return [
            'value' => [
                'display' => static::label(),
                'type' => 'select',
                'clearable' => true,
                'placeholder' => __('directory::cp.filter_any'),
                'options' => [
                    'yes' => __('directory::cp.yes'),
                    'no' => __('directory::cp.no'),
                ],
            ],
        ];
    }

    public function apply($query, $values)
    {
        $value = $values['value'] ?? null;

        if (! in_array($value, ['yes', 'no'], true)) {
            return;
        }

        $this->constrain($query, $value === 'yes');
    }

    public function badge($values)
    {
        $value = $values['value'] ?? null;

        return static::label().': '.($value === 'yes' ? __('directory::cp.yes') : __('directory::cp.no'));
    }
}
