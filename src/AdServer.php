<?php

class AdServer {
    /**
     * Stores the index where parser currently is, disreegarding recursion
     */
    private int $index;

    /**
     * Weights the number of brackets (in order to throw an error if brackets do not match)
     */
    private int $bracketsCounter;

    /**
     * Should enable/disable numeric intervals feature for testing without it
     */
    private bool $withNumericIntervals;

    /**
     * Should enable/disable string array feature for testing without it
     */
    private bool $withStringArray;

    /**
     * AdServer constructor.
     *
     * @param boolean $withNumericIntervals
     * @param boolean $withStringArray
     */
    public function __construct(bool $withNumericIntervals = true, bool $withStringArray = true) {
        $this->index = 0;
        $this->withNumericIntervals = $withNumericIntervals;
        $this->withStringArray = $withStringArray;
        $this->bracketsCounter = 0;
    }

    /**
     * Determine if the ad should be served
     * @param array $publisherKeyValues
     * @param string $advertiserConditions
     * @return bool
     */
    public function shouldAdBeServed(array $publisherKeyValues, string $advertiserConditions): bool
    {
        if(count($publisherKeyValues) == 0) {
            // or throw an exception regarding no values being passed
            return false;
        }

        if(strlen($advertiserConditions) == 0) {
            // or throw an exception regarding no conditions given
            return true;
        }

        // Replace textual representations of these operators with their sign values for easier parsing
        $advertiserConditions = str_replace(" and ", " && ", $advertiserConditions);
        $advertiserConditions = str_replace(" or ", " || ", $advertiserConditions);
        $advertiserConditions = str_replace(" = ", "=", $advertiserConditions);

        // Wrap all conditions in parenthesis
        $advertiserConditions = "($advertiserConditions)";

        // Do the parsing and return the result
        $this->index = 0;
        $this->bracketsCounter = 0;
        return $this->parseAndEvaluateConditions($publisherKeyValues, $advertiserConditions);
    }

    /**
     * Evaluates the scope variable based on expression value and relation parameters
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
        } else {
            $message = $relation ?? "'null'";
            throw new Exception("Invalid logical operator, expecting [and, or] got $message");
        }
    }

    /**
     * Determines if the char is in the given array of defined comparators
     *
     * @param string $char
     * @return boolean
     */
    private function shouldStartComparison(string $char) : bool {
        return in_array($char, [
            '<',
            '>',
            "=",
        ]);
    }

    /**
     * Determines if the string represents numeric interval [x-y]
     *
     * @param string $interval
     * @return boolean
     */
    private function isNumericInterval(string $interval) : bool {
        $count = strlen($interval);
        return $count != 0 && 
                $interval[0] == '[' && 
                $interval[$count - 1] == ']' &&
                strpos($interval, "-") !== false;
    }

    /**
     * Parses numeric interval [x-y] to array [x, y] and determines if value is in those boundaries
     *
     * @param string $interval
     * @return void
     */
    private function parseAndEvaluateNumericInterval(int $value, string $interval) : bool {
        if(!$this->isNumericInterval($interval)) {
            throw new Exception("Cannot parse non numeric interval string as numeric interval");
        }

        $parts = explode("-", $interval);

        if(count($parts) != 2) {
            throw new Exception("Non-valid numeric interval at position $this->index");
        }

        $bottomBoundary = substr($parts[0], 1, strlen($parts[0]) - 1);
        $topBoundary = substr($parts[1], 0, strlen($parts[1]) - 1);

        if(!is_numeric($bottomBoundary) || !is_numeric($topBoundary)) {
            throw new Exception("Non-valid argumest passed to numeric interval at position $this->index");
        }

        $bottomBoundary = intval($bottomBoundary);
        $topBoundary = intval($topBoundary);

        return $value >= $bottomBoundary && $value <= $topBoundary;
    }

    /**
     * Determines if the input string should be parse as a string array by the parser
     *
     * @param string $input
     * @return boolean
     */
    private function isStringArray(string $input) : bool {
        return strpos($input, ",") !== false;
    }

    /**
     * Determine if the value is inside input (which will be converted to string array)
     *
     * @param string $value
     * @param string $input
     * @return boolean
     */
    private function parseAndEvaluateStringArray(string $value, string $input) : bool
    {
        if(!$this->isStringArray($input)) {
            throw new Exception("Tried evaluating non string array");
        }

        $values = explode(",", $input);

        return in_array($value, $values);
    }

    /**
     * Evaluate input string
     * Scope = (...), anything between a set of parenthesis. It is defined by multiple expressions (or none)
     * Expression = param=val, represents a part of the scope
     * @param string $conditions
     * @return bool
     */
    private function parseAndEvaluateConditions(array $keyValues, string $conditions, int $index = 0) : bool {
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
                $this->bracketsCounter++;
                // Opening bracket opens the scope, thus we call the recursion and apply the same logic to the underlaying scope
                $innerScope = $this->parseAndEvaluateConditions($keyValues, $conditions, ++$this->index);
                
                // Evaluate current scope with evaluated brackets
                $this->evaluateScope($scopedEval, $innerScope, $expectedRelation);
                $expectedRelation = null;
            } else if($conditions[$this->index] == ')') {
                if($this->bracketsCounter == 0) {
                    throw new Exception("Unexpected ')' at position $this->index. It has no matching opening parenthesis.");
                }
                $this->bracketsCounter--;
                // Finish the scope and return it's value
                return $scopedEval ?? true;
            } else {
                // Get char in advance to handle signs when comparing, because index gets changed
                $char = $conditions[$this->index];

                if($this->shouldStartComparison($char)) {
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
                    if($this->index + 1 < $max && $char != '=' && $conditions[$this->index + 1] == "="){
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

                    // If we have read the comparison value up until the closing bracket, decrease the index, so the next iteration can catch the bracket and end the scope
                    if($this->index < $max && $conditions[$this->index] == ')') {
                        $this->index--;
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
                            $expressionEval = $valueFromKeyValues < $comparisonValue;
                        } else if($sign == '>') {
                            $expressionEval = $valueFromKeyValues > $comparisonValue;
                        } else if($sign == '>=') {
                            $expressionEval = $valueFromKeyValues >= $comparisonValue;
                        } else if($sign == '<=') {
                            $expressionEval = $valueFromKeyValues <= $comparisonValue;
                        } else if ($sign == '=') {
                            $expressionEval = $valueFromKeyValues == $comparisonValue;
                        }
                    } else if($this->withNumericIntervals && $this->isNumericInterval($comparisonValue)) {
                        // Handle wrong type comparison
                        if(!is_numeric($valueFromKeyValues)) {
                            throw new Exception("Cannot compare " . gettype($valueFromKeyValues) . " with numeric interval at position $this->index");
                        }

                        $expressionEval = $this->parseAndEvaluateNumericInterval($valueFromKeyValues, $comparisonValue);
                    } else if($this->withStringArray && $this->isStringArray($comparisonValue)) {
                        // Handle wrong type comparison
                        if(!is_string($valueFromKeyValues)) {
                            throw new Exception("Cannot compare " . gettype($valueFromKeyValues) . " with string array at position $this->index");
                        }

                        $expressionEval = $this->parseAndEvaluateStringArray($valueFromKeyValues, $comparisonValue);
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
                    $expectedRelation = null;

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

        if($this->bracketsCounter != 0) {
            throw new Exception("Brackets missmatch in logical expression");
        }

        return $scopedEval ?? true;
    }
}

?>