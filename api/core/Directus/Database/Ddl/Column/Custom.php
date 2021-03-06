<?php

namespace Directus\Database\Ddl\Column;

use Zend\Db\Sql\Ddl\Column\AbstractPrecisionColumn;

class Custom extends AbstractPrecisionColumn
{
    public function setType($type)
    {
        $this->type = $type;
    }

    /**
     * Sets the column length
     *
     * @param int|array $length
     *
     * @return $this
     */
    public function setLength($length)
    {
        if (is_array($length)) {
            $length = implode(',', array_map(function ($value) {
                // add slashes in case the value has quotes
                return sprintf('"%s"', addslashes($value));
            }, $length));
        } else {
            $length = (int) $length;
        }

        $this->length = $length;

        return $this;
    }
}
