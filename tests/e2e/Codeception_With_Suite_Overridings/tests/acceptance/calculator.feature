Feature: Calculator
  In order to perform mathematical operations
  As a user
  I need to be able to use the calculator

  Scenario: Adding two numbers
    Given I have a calculator
    When I add 2 and 3
    Then the result should be 5

  Scenario: Subtracting two numbers
    Given I have a calculator
    When I subtract 3 from 10
    Then the result should be 7

  Scenario: Multiplying two numbers
    Given I have a calculator
    When I multiply 4 by 5
    Then the result should be 20

  Scenario: Dividing two numbers
    Given I have a calculator
    When I divide 15 by 3
    Then the result should be 5

  Scenario: Division by zero throws error
    Given I have a calculator
    When I try to divide 10 by 0
    Then I should get an error "Division by zero"

  Scenario Outline: Checking if numbers are positive
    Given I have a calculator
    When I check if <number> is positive
    Then the result should be <expected>

    Examples:
      | number | expected |
      | 5      | true     |
      | 0      | true     |
      | -5     | false    |

  Scenario Outline: Computing absolute value with label "<label>"
    Given I have a calculator
    When I compute the absolute value of <number>
    Then the result should be <expected>

    Examples:
      | label                       | number | expected |
      | 42                          | 5      | 5        |
      | positive number             | 10     | 10       |
      | negative number             | -7     | 7        |
      | zero                        | 0      | 0        |
      | with special chars ('"#::&) | -15    | 15       |
      | another "quoted" value      | -1     | 1        |