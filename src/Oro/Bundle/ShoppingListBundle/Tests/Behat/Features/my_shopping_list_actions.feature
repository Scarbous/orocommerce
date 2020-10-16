@regression
@ticket-BB-19469
@fixture-OroShoppingListBundle:MyShoppingListsFixture.yml
@fixture-OroFlatRateShippingBundle:FlatRateIntegration.yml
@fixture-OroCheckoutBundle:Shipping.yml
@fixture-OroPaymentTermBundle:PaymentTermIntegration.yml
@fixture-OroCheckoutBundle:Payment.yml

Feature: My Shopping List Actions
  In order to manage shopping lists on front store
  As a Buyer
  I need to be able to manage shopping list using actions on shopping list view page

  Scenario: Feature Background
    Given I set configuration property "oro_shopping_list.my_shopping_lists_page_enabled" to "1"

  Scenario: Check index page
    Given I login as AmandaRCole@example.org buyer
    When I follow "Account"
    And I click "My Shopping Lists"
    Then Page title equals to "My Shopping Lists - My Account"
    And I should see following grid:
      | Name            | Subtotal  | Items | Default |
      | Shopping List 3 | $8,818.00 | 32    | Yes     |
      | Shopping List 1 | $1,581.00 | 3     | No      |
    And records in grid should be 2
    When I open shopping list widget
    Then I should see "Shopping List 1" on shopping list widget
    And I should see "Shopping List 2" on shopping list widget
    And I should see "Shopping List 3" on shopping list widget
    And I reload the page

  Scenario: Duplicate Action
    Given I click View "Shopping List 3" in grid
    When I click "Shopping List Actions"
    And I click "Duplicate"
    And I click "Yes, duplicate"
    Then I should see "The shopping list has been duplicated" flash message
    When I open shopping list widget
    Then I should see "Shopping List 3 (Copied" in the "Shopping List Widget" element
    And I reload the page

  Scenario: Rename Action
    Given I click "Shopping List Actions"
    When I click "Rename"
    And I fill "Shopping List Rename Action Form" with:
      | Label | Shopping List 4 |
    And I click "Shopping List Action Submit"
    Then I should see "Shopping list has been successfully renamed" flash message
    When I open shopping list widget
    Then I should see "Shopping List 4" on shopping list widget
    And I reload the page

  Scenario: Set Default Action
    Given I click "Shopping List Actions"
    Then I should see "Set as Default"
    When I click "Set as Default"
    And I click "Yes, set as default"
    Then I should see "Shopping list has been successfully set as default" flash message
    When I click "Shopping List Actions"
    Then I should not see "Set as Default"
    When I open shopping list widget
    And I click on "Shopping List Widget Set Current Radio 2"
    And I close shopping list widget
    And I click "Shopping List Actions"
    Then I should see "Set as Default"

  Scenario: Check Default Shopping List
    When I follow "Account"
    And I click "My Shopping Lists"
    Then I should see following grid:
      | Name            | Subtotal  | Items | Default |
      | Shopping List 4 | $8,818.00 | 32    | No      |
      | Shopping List 3 | $8,818.00 | 32    | No      |
      | Shopping List 1 | $1,581.00 | 3     | Yes     |
    And records in grid should be 3

  Scenario: Add Shopping List notes
    When I click Edit "Shopping List 4" in grid
    And I click on "Add a note to entire Shopping List"
    And I type "My shopping list notes" in "Shopping List Notes Area"
    And I click "Apply"
    Then I should see "My shopping list notes" in the "Shopping List Notes" element

  Scenario: Edit Shopping List notes and Line item notes
    When I click on "Edit Shopping List Notes"
    And I type "My shopping list updated notes" in "Shopping List Notes Area"
    And I click "Apply"
    Then I should see "My shopping list updated notes" in the "Shopping List Notes" element
    And I should see following grid:
      | SKU | Item                               |
      | BB4 | Configurable Product 1 Note 4 text |
    When I click "Edit Shopping List Line Item Note"
    Then I should see "UiWindow" with elements:
      | Title        | Edit note for "Configurable Product 1" product |
      | okButton     | Save                                           |
      | cancelButton | Cancel                                         |
    When I type "Note 4 text updated" in "Line Item Notes Area"
    And click "Save" in modal window
    Then should see "Line item note has been successfully updated" flash message
    And I should see following grid:
      | SKU | Item                                       |
      | BB4 | Configurable Product 1 Note 4 text updated |

  Scenario: Delete Action
    When I click "Shopping List Actions"
    And I click "Delete"
    And I click "Yes, delete"
    Then Page title equals to "My Shopping Lists - My Account"
    When I open shopping list widget
    Then I should not see "Shopping List 4" on shopping list widget
    And I reload the page

  Scenario: Move line item to another shopping list
    Given I follow "Account"
    And I click "My Shopping Lists"
    And Page title equals to "My Shopping Lists - My Account"
    And I should see following grid:
      | Name            | Subtotal  | Items | Default |
      | Shopping List 3 | $8,818.00 | 32    | No      |
      | Shopping List 1 | $1,581.00 | 3     | Yes     |
    And I click Edit "Shopping List 3" in grid
    When I click on "First Line Item Row Checkbox"
    And I click "Move to another Shopping List" link from mass action dropdown
    And I click "Filter Toggle" in "UiDialog" element
    And I filter Name as is equal to "Shopping List 1" in "Shopping List Action Move Grid"
    And I click "Shopping List Action Move Radio"
    And I click "Shopping List Action Submit"
    Then I should see "One entity has been moved successfully" flash message
    And I follow "Account"
    And I click "My Shopping Lists"
    And Page title equals to "My Shopping Lists - My Account"
    And I should see following grid:
      | Name            | Subtotal  | Items | Default |
      | Shopping List 3 | $8,785.00 | 31    | No      |
      | Shopping List 1 | $1,614.00 | 4     | Yes     |

  Scenario: Re-assign Action
    Given I click View "Shopping List 3" in grid
    When I click "Shopping List Actions"
    And I click "Reassign"
    And I filter First Name as is equal to "Nancy" in "Shopping List Action Reassign Grid"
    And I click "Shopping List Action Reassign Radio"
    And I click "Shopping List Action Submit"
    Then I should see "Nancy Sallee"
    When I click "Nancy Sallee"
    Then Page title equals to "Nancy Sallee - Users - My Account"

  Scenario: Check shopping list view page without actions
    Given I follow "Account"
    When click "Users"
    And click "Roles"
    And click edit "Administrator" in grid
    And click "Shopping"
    And select following permissions:
      | Shopping List | Edit:None      |
      | Shopping List | Assign:None    |
      | Shopping List | Duplicate:None |
      | Shopping List | Delete:None    |
    And I scroll to top
    And click "Save"
    Then should see "Customer User Role has been saved" flash message
    When click "Sign Out"
    And I login as AmandaRCole@example.org buyer
    And I follow "Account"
    And I click "My Shopping Lists"
    And I click View "Shopping List 1" in grid
    Then I should not see a "Shopping List Actions" element