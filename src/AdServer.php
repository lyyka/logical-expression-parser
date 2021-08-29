<?php

class AdServer {
    /**
     * Stores the index where parser currently is, disreegarding recursion
     */
    private int $index;

    public function __construct() {
        // Set the starting index to always be null
        $this->index = 0;
    }

    /**
     * Determine if the ad should be served
     * @param array $publisherKeyValues
     * @param string $advertiserConditions
     * @return bool
     */
    public function shouldAdBeServed(array $publisherKeyValues, string $advertiserConditions): bool
    {
        // Replace textual representations of these operators with their sign values for easier parsing
        $advertiserConditions = str_replace(" and ", " && ", $advertiserConditions);
        $advertiserConditions = str_replace(" or ", " || ", $advertiserConditions);
        $advertiserConditions = str_replace(" = ", "=", $advertiserConditions);
        $advertiserConditions = "($advertiserConditions)";

        // Do the parsing and return the result
        $this->index = 0;
        // echo("(");
        $res = $this->parseConditions($publisherKeyValues, $advertiserConditions);
        // echo(")");

        return $res;
    }

    /**
     * Evaluates the scope variable based on value and relation parameters
     * @param bool|null $scope
     * @param bool $value
     * @param string|null $relation
     */
    private function evaluateScope(?bool &$scope, bool $value, ?string $relation) {
        if($scope === null) {
            $scope = $value;
        } else if($relation != null) {
            if($relation == 'and') {
                $scope = $scope && $value;
            } else if($relation == 'or') {
                $scope = $scope || $value;
            }
        }
    }

    /**
     * Evaluate input string
     * Scope = (...)
     * Expression = param=val
     * Scope value is defined by one or more expressions
     * @param string $conditions
     * @return bool
     */
    private function parseConditions(array $keyValues, string $conditions, int $index = 0) : bool {
        // Store the scope value, start with null so we can tell that we just began the scope
        $scopedEval = null;

        // Max number of iterations
        $max = strlen($conditions);

        // Buffer which will read parameter names
        $buffer = "";

        // This variable will notify any expression if it is part of some and/or conditions
        $expectedRelation = null;

        // Notice that index is globally defined, so it won't change during recursions and recvert back
        for($this->index = $index; $this->index < $max; $this->index++) {
            if($conditions[$this->index] == '(') {
                // Opening bracket opens the scope, thus we call the recursion and apply the same logic to the underlaying scope
                $innerScope = $this->parseConditions($keyValues, $conditions, ++$this->index);
                
                // Evaluate current scope with evaluated brackets
                $this->evaluateScope($scopedEval, $innerScope, $expectedRelation);
            } else if($conditions[$this->index] == ')') {
                // Finish the scope and return it's value
                return $scopedEval ?? true;
            } else {
                // Get char in advance to handle signs when comparing, because index gets changed
                $char = $conditions[$this->index];

                if(in_array($char, [
                    '<',
                    '>',
                    "=",
                ])) {
                    // At this point, comparison should happen
                    // Buffer will be the array key (parameter name)

                    // If keyValues does not contain buffer, that means that the query contains parameters that are not defined
                    // Return false in this case
                    if(!array_key_exists($buffer, $keyValues)) {
                        return false;
                    }

                    // Value before operand (before opreator)
                    $valueFromKeyValues = $keyValues[$buffer];

                    // Skip one more char for <= and >= signs, so the '=' after >/< gets skipped
                    $sign = $char;
                    if($this->index + 1 < $max && $char != '=' &&  $conditions[$this->index + 1] == "="){
                        $sign .= '=';
                        $this->index++;
                    } else if($this->index + 1 == $max) {
                        throw new Exception("Expected comparison value after the operator at the end of the string.");
                    }

                    // Get value after operand (everything after operator) (read string until space or closing bracket)
                    $comparisonValue = "";
                    for(++$this->index; $this->index < $max && $conditions[$this->index] != ' ' && $conditions[$this->index] != ')'; $this->index++) {
                        $comparisonValue .= $conditions[$this->index];
                    }

                    // This variable will store value for this specific expression
                    $expressionEval = null;
                    
                    if(is_numeric($comparisonValue)) {
                        // If we have an integer value, handle it

                        // Handle wrong type comparison
                        if(!is_numeric($valueFromKeyValues)) {
                            throw new Exception("Cannot compare " . gettype($valueFromKeyValues) . " with numeric value at position $this->index");
                        }

                        // Convert value from string
                        $comparisonValue = floatval($comparisonValue);

                        // Take sign into account and set current expression value
                        if($sign == '<') {
                            // echo("$valueFromKeyValues < $comparisonValue \n");
                            $expressionEval = $valueFromKeyValues < $comparisonValue;
                        } else if($sign == '>') {
                            // echo("$valueFromKeyValues > $comparisonValue \n");
                            $expressionEval = $valueFromKeyValues > $comparisonValue;
                        } else if($sign == '>=') {
                            // echo("$valueFromKeyValues >= $comparisonValue \n");
                            $expressionEval = $valueFromKeyValues >= $comparisonValue;
                        } else if($sign == '<=') {
                            // echo("$valueFromKeyValues <= $comparisonValue \n");
                            $expressionEval = $valueFromKeyValues <= $comparisonValue;
                        } else if ($sign == '=') {
                            // echo("$valueFromKeyValues = $comparisonValue \n");
                            $expressionEval = $valueFromKeyValues == $comparisonValue;
                        }
                    } else {
                        // If we have a string value, handle it

                        // Handle wrong type comparison
                        if(!is_string($valueFromKeyValues)) {
                            throw new Exception("Cannot compare " . gettype($valueFromKeyValues) . " with string value at position $this->index");
                        }

                        // Cannot compare strings other then = (this can be implemented through ascii, or alphabetical order)
                        if($sign != '=') {
                            throw new Exception("Cannot compare two strings with operator different then '=' at position $this->index");
                        }

                        // Set current expression value
                        $expressionEval = $valueFromKeyValues == $comparisonValue;
                    }

                    // Re-evaluate the current scope with new expression value we now have
                    $this->evaluateScope($scopedEval, $expressionEval, $expectedRelation);

                    // Clear the buffer to make room for next parameter to load
                    $buffer = "";
                } else if($char == '&') {
                    // Idea is to hint to next expression that it belongs to this relation
                    $expectedRelation = 'and';
                    $this->index++;
                } else if($char == '|') {
                    // Idea is to hint to next expression that it belongs to this relation
                    $expectedRelation = 'or';
                    $this->index++;
                } else {
                    // Add only letters to buffers (only letters will be parameters, since 'and/or' are exchanged with signs '&' and '|')
                    if($conditions[$this->index] != ' ') {
                        $buffer .= $conditions[$this->index];
                    }
                }
            }
        }

        return $scopedEval ?? true;
    }
}

?>