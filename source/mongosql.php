<?php
class MongoSQLConverter {
    const NL = '<br/>';
    
    private static $sqlOperators = [
        '=' => '$eq',
        '!=' => '$ne',
        '>' => '$gt',
        '>=' => '$gte',
        '<' => '$lt',
        '<=' => '$lte',
        'in' => '$in',
        'not in' => '$nin',
        'like' => '$regex'
    ];
    
    private static $mongoAggregationOperators = [
        'sum' => '$sum',
        'avg' => '$avg',
        'min' => '$min',
        'max' => '$max',
        'count' => '$sum',
        'distinct' => '$addToSet'
    ];
    
    public static function convert($sql) {
        $sql = trim($sql);
        $sqlLower = strtolower($sql);
        
        if (strpos($sqlLower, 'select') === 0) {
            return self::convertSelect($sql);
        } elseif (strpos($sqlLower, 'insert') === 0) {
            return self::convertInsert($sql);
        } elseif (strpos($sqlLower, 'update') === 0) {
            return self::convertUpdate($sql);
        } elseif (strpos($sqlLower, 'delete') === 0) {
            return self::convertDelete($sql);
        } else {
            throw new Exception("Unsupported SQL statement type");
        }
    }
    
    private static function parseConditions($whereClause) {
        $conditions = [];
        $whereClause = trim($whereClause);
        
        // Handle AND/OR conditions
        $andParts = preg_split('/\s+AND\s+/i', $whereClause);
        foreach ($andParts as $andPart) {
            $orParts = preg_split('/\s+OR\s+/i', $andPart);
            if (count($orParts) > 1) {
                $orConditions = [];
                foreach ($orParts as $orPart) {
                    $orConditions[] = self::parseSingleCondition($orPart);
                }
                $conditions[] = ['$or' => $orConditions];
            } else {
                $conditions = array_merge($conditions, self::parseSingleCondition($andPart));
            }
        }
        
        return $conditions;
    }
    
    private static function parseSingleCondition($condition) {
        $condition = trim($condition);
        
        // Handle parentheses
        if (preg_match('/^\((.*)\)$/', $condition, $matches)) {
            return self::parseConditions($matches[1]);
        }
        
        // Handle operators
        foreach (self::$sqlOperators as $sqlOp => $mongoOp) {
            if (strpos($condition, " $sqlOp ") !== false) {
                $parts = explode(" $sqlOp ", $condition);
                $field = trim($parts[0]);
                $value = trim($parts[1]);
                
                // Remove quotes if present
                if (preg_match('/^["\'](.*)["\']$/', $value, $matches)) {
                    $value = $matches[1];
                }
                
                // Special handling for LIKE
                if ($sqlOp === 'like') {
                    $value = str_replace('%', '.*', $value);
                    $value = '^' . $value . '$';
                    return [$field => [ $mongoOp => $value, '$options' => 'i' ]];
                }
                
                // Handle IN/NOT IN
                if (in_array($sqlOp, ['in', 'not in'])) {
                    $values = explode(',', substr($value, 1, -1));
                    $values = array_map('trim', $values);
                    $values = array_map(function($v) {
                        return preg_match('/^["\'](.*)["\']$/', $v, $m) ? $m[1] : $v;
                    }, $values);
                    return [$field => [ $mongoOp => $values ]];
                }
                
                return [$field => [ $mongoOp => $value ]];
            }
        }
        
        return [$condition => true];
    }
    
    public static function convertSelect($sql) {
        $sql = preg_replace('/\s+/', ' ', trim($sql));
        $sqlLower = strtolower($sql);
        
        // Extract clauses using regex
        $pattern = '/^select\s+(.*?)\s+from\s+([^\s(]+)(?:\s+where\s+(.*?))?(?:\s+group by\s+(.*?))?(?:\s+having\s+(.*?))?(?:\s+order by\s+(.*?))?(?:\s+limit\s+(\d+)(?:\s*,\s*(\d+))?)?$/i';
        
        if (!preg_match($pattern, $sqlLower, $matches)) {
            throw new Exception("Invalid SELECT statement format");
        }
        
        $fields = trim($matches[1]);
        $collection = trim($matches[2]);
        $where = isset($matches[3]) ? trim($matches[3]) : null;
        $groupBy = isset($matches[4]) ? trim($matches[4]) : null;
        $having = isset($matches[5]) ? trim($matches[5]) : null;
        $orderBy = isset($matches[6]) ? trim($matches[6]) : null;
        $limit = isset($matches[7]) ? (int)$matches[7] : null;
        $skip = isset($matches[8]) ? (int)$matches[8] : null;
        
        // Handle aggregation if GROUP BY is present
        if ($groupBy) {
            return self::convertAggregateQuery($collection, $fields, $where, $groupBy, $having, $orderBy, $limit, $skip);
        }
        
        // Regular find query
        $mongoQuery = "db.$collection";
        
        // WHERE conditions
        $queryConditions = [];
        if ($where) {
            $queryConditions = self::parseConditions($where);
        }
        
        // Projection
        $projection = [];
        if ($fields !== '*') {
            $fieldList = explode(',', $fields);
            foreach ($fieldList as $field) {
                $field = trim($field);
                if (strpos($field, ' as ') !== false) {
                    list($original, $alias) = explode(' as ', $field);
                    $projection[trim($alias)] = 1;
                } else {
                    $projection[$field] = 1;
                }
            }
        }
        
        // Build query
        if (!empty($queryConditions)) {
            $mongoQuery .= ".find(" . json_encode($queryConditions, JSON_PRETTY_PRINT) . ")";
        } else {
            $mongoQuery .= ".find()";
        }
        
        // Projection
        if (!empty($projection)) {
            $mongoQuery .= ".projection(" . json_encode($projection, JSON_PRETTY_PRINT) . ")";
        }
        
        // ORDER BY
        if ($orderBy) {
            $sort = [];
            $orderParts = explode(',', $orderBy);
            foreach ($orderParts as $part) {
                $part = trim($part);
                if (preg_match('/(.*)\s+(asc|desc)$/i', $part, $m)) {
                    $sort[$m[1]] = strtolower($m[2]) === 'desc' ? -1 : 1;
                } else {
                    $sort[$part] = 1;
                }
            }
            $mongoQuery .= ".sort(" . json_encode($sort, JSON_PRETTY_PRINT) . ")";
        }
        
        // LIMIT and OFFSET
        if ($limit !== null) {
            if ($skip !== null) {
                $mongoQuery .= ".skip($skip)";
            }
            $mongoQuery .= ".limit($limit)";
        }
        
        return $mongoQuery;
    }
    
    private static function convertAggregateQuery($collection, $fields, $where, $groupBy, $having, $orderBy, $limit, $skip) {
        $pipeline = [];
        
        // $match stage for WHERE
        if ($where) {
            $matchConditions = self::parseConditions($where);
            $pipeline[] = ['$match' => $matchConditions];
        }
        
        // $group stage
        $groupFields = explode(',', $groupBy);
        $groupFields = array_map('trim', $groupFields);
        
        $groupStage = ['_id' => []];
        foreach ($groupFields as $field) {
            $groupStage['_id'][$field] = '$' . $field;
        }
        
        // Parse SELECT fields (aggregation functions)
        $fieldList = explode(',', $fields);
        foreach ($fieldList as $field) {
            $field = trim($field);
            if (preg_match('/(\w+)\((.*?)\)(?:\s+as\s+(\w+))?/i', $field, $matches)) {
                $func = strtolower($matches[1]);
                $column = $matches[2];
                $alias = isset($matches[3]) ? $matches[3] : $func . '_' . $column;
                
                if (isset(self::$mongoAggregationOperators[$func])) {
                    $groupStage[$alias] = [self::$mongoAggregationOperators[$func] => '$' . $column];
                }
            } elseif (!in_array($field, $groupFields)) {
                $groupStage[$field] = 1;
            }
        }
        
        $pipeline[] = ['$group' => $groupStage];
        
        // $match stage for HAVING
        if ($having) {
            $havingConditions = self::parseConditions($having);
            $pipeline[] = ['$match' => $havingConditions];
        }
        
        // $sort stage
        if ($orderBy) {
            $sort = [];
            $orderParts = explode(',', $orderBy);
            foreach ($orderParts as $part) {
                $part = trim($part);
                if (preg_match('/(.*)\s+(asc|desc)$/i', $part, $m)) {
                    $sort[$m[1]] = strtolower($m[2]) === 'desc' ? -1 : 1;
                } else {
                    $sort[$part] = 1;
                }
            }
            $pipeline[] = ['$sort' => $sort];
        }
        
        // $limit and $skip stages
        if ($skip !== null) {
            $pipeline[] = ['$skip' => $skip];
        }
        if ($limit !== null) {
            $pipeline[] = ['$limit' => $limit];
        }
        
        return "db.$collection.aggregate(" . json_encode($pipeline, JSON_PRETTY_PRINT) . ")";
    }
    
    public static function convertInsert($sql) {
        $sql = preg_replace('/\s+/', ' ', trim($sql));
        $sqlLower = strtolower($sql);
        
        if (!preg_match('/^insert\s+into\s+([^\s(]+)\s*(?:\((.*?)\))?\s*values\s*\((.*?)\)/i', $sqlLower, $matches)) {
            throw new Exception("Invalid INSERT statement format");
        }
        
        $collection = trim($matches[1]);
        $columns = isset($matches[2]) ? explode(',', trim($matches[2])) : [];
        $values = explode(',', trim($matches[3]));
        
        $document = [];
        if (!empty($columns)) {
            // Insert with column names specified
            foreach ($columns as $i => $column) {
                $column = trim($column);
                $value = trim($values[$i]);
                $document[$column] = self::parseValue($value);
            }
        } else {
            // Insert without column names (must match collection schema)
            foreach ($values as $i => $value) {
                $document["field$i"] = self::parseValue(trim($value));
            }
        }
        
        return "db.$collection.insertOne(" . json_encode($document, JSON_PRETTY_PRINT) . ")";
    }
    
    public static function convertUpdate($sql) {
        $sql = preg_replace('/\s+/', ' ', trim($sql));
        $sqlLower = strtolower($sql);
        
        if (!preg_match('/^update\s+([^\s]+)\s+set\s+(.*?)(?:\s+where\s+(.*?))?$/i', $sqlLower, $matches)) {
            throw new Exception("Invalid UPDATE statement format");
        }
        
        $collection = trim($matches[1]);
        $setClause = trim($matches[2]);
        $whereClause = isset($matches[3]) ? trim($matches[3]) : null;
        
        // Parse SET clause
        $updates = [];
        $setParts = explode(',', $setClause);
        foreach ($setParts as $part) {
            $part = trim($part);
            if (preg_match('/([^\s=]+)\s*=\s*(.*)/', $part, $m)) {
                $field = trim($m[1]);
                $value = self::parseValue(trim($m[2]));
                $updates[$field] = $value;
            }
        }
        
        // Parse WHERE clause
        $queryConditions = [];
        if ($whereClause) {
            $queryConditions = self::parseConditions($whereClause);
        }
        
        return "db.$collection.updateMany(" . 
            json_encode($queryConditions, JSON_PRETTY_PRINT) . ", " .
            json_encode(['$set' => $updates], JSON_PRETTY_PRINT) . ")";
    }
    
    public static function convertDelete($sql) {
        $sql = preg_replace('/\s+/', ' ', trim($sql));
        $sqlLower = strtolower($sql);
        
        if (!preg_match('/^delete\s+from\s+([^\s]+)(?:\s+where\s+(.*?))?$/i', $sqlLower, $matches)) {
            throw new Exception("Invalid DELETE statement format");
        }
        
        $collection = trim($matches[1]);
        $whereClause = isset($matches[2]) ? trim($matches[2]) : null;
        
        // Parse WHERE clause
        $queryConditions = [];
        if ($whereClause) {
            $queryConditions = self::parseConditions($whereClause);
        } else {
            // If no WHERE clause, delete all documents
            $queryConditions = [];
        }
        
        return "db.$collection.deleteMany(" . json_encode($queryConditions, JSON_PRETTY_PRINT) . ")";
    }
    
    private static function parseValue($value) {
        // Check for string
        if (preg_match('/^["\'](.*)["\']$/', $value, $matches)) {
            return $matches[1];
        }
        
        // Check for numbers
        if (is_numeric($value)) {
            return strpos($value, '.') !== false ? (float)$value : (int)$value;
        }
        
        // Check for boolean
        if (strtolower($value) === 'true') return true;
        if (strtolower($value) === 'false') return false;
        
        // Check for null
        if (strtolower($value) === 'null') return null;
        
        // Assume it's a field reference
        return '$' . $value;
    }
}