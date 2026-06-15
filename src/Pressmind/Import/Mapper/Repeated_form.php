<?php


namespace Pressmind\Import\Mapper;


use Exception;
use Pressmind\ORM\Object\MediaObject\DataType\Repeated_form\Row;
use stdClass;

class Repeated_form implements MapperInterface
{
    /**
     * @param mixed $values
     * @return array
     */
    private function normalizeValues($values): array
    {
        if (is_array($values)) {
            return $values;
        }
        if (is_object($values)) {
            return array_values(get_object_vars($values));
        }
        return [];
    }

    /**
     * @param mixed $columns
     * @return array
     */
    private function normalizeColumns($columns): array
    {
        if (!is_array($columns)) {
            return [];
        }

        $normalized = [];
        foreach ($columns as $index => $column) {
            $sort = isset($column->sort) ? (int)$column->sort : (int)$index;
            $normalized[$sort] = $column;
        }
        ksort($normalized);

        return $normalized;
    }

    /**
     * @param object $column
     * @return string|null
     */
    private function getColumnVarName($column): ?string
    {
        return $column->varName ?? ($column->var_name ?? null);
    }

    /**
     * @param object $column
     * @return string|null
     */
    private function getColumnTitle($column): ?string
    {
        return $column->label ?? ($column->name ?? null);
    }

    /**
     * @param object $column
     * @return bool
     */
    private function isIbeTeaserColumn($column): bool
    {
        return isset($column->type) && $column->type === 'ibe_teaser';
    }

    /**
     * @param object $row
     * @param int $index
     * @return int
     */
    private function getRowSort($row, int $index): int
    {
        if (isset($row->sorting)) {
            return (int)$row->sorting;
        }
        if (isset($row->sort)) {
            return (int)$row->sort;
        }
        return $index;
    }

    /**
     * @param object $row
     * @param int $columnKey
     * @param string|null $varName
     * @return string|null
     */
    private function getRowValue($row, int $columnKey, ?string $varName): ?string
    {
        if ($varName !== null) {
            $value = $this->getConfiguredRowValue($row, $varName);
            if ($value !== null) {
                return $this->normalizeTextValue($value);
            }
        }

        $value_name = 'value_' . $columnKey . '_string';
        return isset($row->$value_name) ? $this->normalizeTextValue($row->$value_name) : null;
    }

    /**
     * @param object $row
     * @param string $name
     * @return mixed|null
     */
    private function getConfiguredRowValue($row, string $name)
    {
        if (isset($row->values) && is_object($row->values) && property_exists($row->values, $name)) {
            return $row->values->$name;
        }

        return null;
    }

    /**
     * @param mixed $value
     * @return string|null
     */
    private function normalizeTextValue($value): ?string
    {
        if (is_scalar($value) || $value instanceof \Stringable) {
            return (string)$value;
        }

        return null;
    }

    /**
     * @param int $sort
     * @param string|null $title
     * @param string|null $varName
     * @param mixed $value
     * @return stdClass
     */
    private function createRowColumn(int $sort, ?string $title, ?string $varName, $value): stdClass
    {
        $repeated_form_row_column = new Row\Column();
        $repeated_form_row_column->sort = $sort;
        $repeated_form_row_column->title = $title;
        $repeated_form_row_column->var_name = $varName;
        $repeated_form_row_column->value_string = $this->normalizeTextValue($value);
        $repeated_form_row_column->datatype = 'string';

        return $repeated_form_row_column->toStdClass();
    }

    /**
     * @param object $row
     * @param object $column
     * @param int $sort
     * @return array
     */
    private function mapIbeTeaserColumns($row, $column, int $sort): array
    {
        $var_name = $this->getColumnVarName($column);
        if ($var_name === null || $var_name === '') {
            return [];
        }

        $template_value_name = $var_name . '_template';
        $link_value_name = $var_name . '_link';
        $template = $this->getConfiguredRowValue($row, $template_value_name);
        $link = $this->getConfiguredRowValue($row, $link_value_name);

        if ($template === null) {
            $template = $column->template ?? 'teaser';
        }
        if ($link === null) {
            $link = $column->link ?? '';
        }

        $title = $this->getColumnTitle($column);

        return [
            $this->createRowColumn($sort, trim(($title ?? $var_name) . ' Template'), $template_value_name, $template),
            $this->createRowColumn($sort + 1, trim(($title ?? $var_name) . ' Link'), $link_value_name, $link),
        ];
    }

    /**
     * @param int $pIdMediaObject
     * @param string $pLanguage
     * @param string $pVarName
     * @param array|null $pObject
     * @return array
     * @throws Exception
     */
    public function map($pIdMediaObject, $pLanguage, $pVarName, $pObject)
    {
        if (is_null($pObject)) {
            return [];
        }

        $values = $this->normalizeValues($pObject['values'] ?? null);
        if (count($values) === 0) {
            return [];
        }

        $mapped_object = new stdClass();
        $mapped_object->id = null;
        $mapped_object->id_media_object = $pIdMediaObject;
        $mapped_object->section_name = '';
        $mapped_object->language = $pLanguage;
        $mapped_object->var_name = $pVarName;
        $mapped_object->rows = [];

        $columns = $this->normalizeColumns($pObject['columns'] ?? []);
        foreach ($values as $index => $row) {
            $repeated_form_row = new Row();
            $repeated_form_row->sort = $this->getRowSort($row, $index);
            $repeated_form_row->valid_from = $row->validFrom ?? null;
            $repeated_form_row->valid_to = $row->validTo ?? null;
            $repeated_form_row_columns = [];
            $column_sort = 1;

            foreach ($columns as $key => $column) {
                if ($this->isIbeTeaserColumn($column)) {
                    foreach ($this->mapIbeTeaserColumns($row, $column, $column_sort) as $mapped_column) {
                        $repeated_form_row_columns[] = $mapped_column;
                        $column_sort++;
                    }
                    continue;
                }

                $var_name = $this->getColumnVarName($column);
                $repeated_form_row_columns[] = $this->createRowColumn(
                    $column_sort,
                    $this->getColumnTitle($column),
                    $var_name,
                    $this->getRowValue($row, $key, $var_name)
                );
                $column_sort++;
            }

            $repeated_form_row->columns = $repeated_form_row_columns;
            $mapped_object->rows[] = $repeated_form_row->toStdClass();
        }

        return [$mapped_object];
    }
}
