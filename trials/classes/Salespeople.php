<?php

/**
* Things to implement in this file:
* - SalesHiearchy::build()
* - Salesperson::get_best_sales_rep()
* - The success_rate() method in each Salesperson subclass
*/

/**
* An accessor object to add leads to a sales hierarchy optimally and
* inspect the total amount of risk currently being taken on.
*/
class SalesHierarchy
{
	/**
	* Given the legacy salesperson hierarchy format, this returns
	* a hierarchy object that matches. See Greed.php for the specification.
	* @param string - the sales hierarchy
	* @return SalesHierarchy
	*/
	public static function build($sales_hierarchy_string)
	{
        // 1{Ricky|Clueless}
        // "0{Blake|Sociopath}0{Ricky|Clueless}1{Dave|Loser}0{Shelley|Clueless}1{Williamson|Loser}"
        $pattern = '/(0|1){([a-zA-Z]+)\|([a-zA-Z]+)}/';
        $matches = [];
        $nummatches = preg_match_all($pattern, $sales_hierarchy_string, $matches);

        if($nummatches === false)
            throw new Exception("error in preg_match_all");

        /*
        print $sales_hierarchy_string . "\n";
        print 'parsed ' . count($matches[0]) . " salespeople\n";
        foreach($matches[0] as $m) {
            print $m . "\n";
        }
        foreach($matches[1] as $m) {
            print $m . "\n";
        }
        */

        /** @var SalesPerson $root */
        $root = null;
        /** @var SalesPerson $current */
        $current = null;

        for ($i = 0; $i < count($matches[0]); $i++) {
            $zeroOrOne = $matches[1][$i];
            $name      = $matches[2][$i];
            $type      = $matches[3][$i];

            //print $zeroOrOne . "\n";

            $salesperson = new $type($name);
            if($root === null) {
                $root = $salesperson;
                $current = $root;
            } else {
                if($zeroOrOne == "0") {
                   if(self::tryAddChild($current, $salesperson))
                       $current = $salesperson;
                    else
                        break;
                }
                else if($zeroOrOne == "1") {
                    if(self::tryAddChild($current, $salesperson)) {
                        while($current !== null && $current->right() !== null)
                            $current = $current->parent();

                        if($current === null)
                            break;
                    }
                }
            }
        }

        return new SalesHierarchy($root);
	}

    /**
     * @param SalesPerson $parent
     * @param SalesPerson $child
     * @return true if added successfully, false otherwise
     */
    private static function tryAddChild($parent, $child) {
        if($parent->left() === null)
            $parent->set_left($child);
        else if($parent->right() == null)
            $parent->set_right($child);
        else
            return false;

        $child->set_parent($parent);
        return true;
    }

	/**
	* @var Salesperson - the top sales guy, who runs everyone.
	*/
	private $root;

	/**
	* @param Salesperson 
	*/
	public function __construct(Salesperson $root)
	{
		$this->root = $root;
	}

	/**
	* @param Lead - a sales lead
	* @return void - the lead should be assigned to one of the Salespeople
	* in the SalesHierarchy. 
	*/
	public function assign_to_best_rep(Lead $lead)
	{
        /*
        print "Finding rep for lead: ". $lead . "\n";
        print "Root: " . $this->root->name() . "\n";
        */
		$rep = $this->root->get_best_sales_rep($lead);
        if($rep === null)
            throw new Exception("Couldn't find rep to take lead");
        //print "Assigning to " . $rep->name() . "\n";
		$rep->set_current_lead($lead);
	}

	/**
	* @return float - the total risk incurred by the company given the distribution
	* of sales leads to salespeople.
	*/
	public function total_risk()
	{
		return $this->root->total_risk_incurred();
	}

    public function __toString() {
        return $this->root->__toString();
    }
}

/**
* A Salesperson abstract class. Concerete subclasses are below.
*/
abstract class Salesperson
{
    protected $name = null;

	/**
	* @var Salesperson - the direct manager of this Salesperson
	*/
	protected $parent = null;

	/**
	* @var Salesperson - one of the two reports to this Salesperson
	*/
	protected $right = null;

	/**
	* @var Salesperson - one of the two reports to this Salesperson
	*/
	protected $left = null;

	/**
	* @var Lead - the current sales lead this Salesperson is working on
	* (note: this is a potential deal for the company, not the person's manager)
	*/
	protected $current_lead = null;

    public function __construct($name)
    {
        $this->name = $name;
    }

    public function name() { return $this->name; }
	public function parent() { return $this->parent; }
	public function left() { return $this->left; }
	public function right() { return $this->right; }

	public function set_right(Salesperson $person) { $this->right = $person; }
	public function set_left(Salesperson $person) { $this->left = $person; }
	public function set_parent(Salesperson $person) { $this->parent = $person; }
	public function set_current_lead(Lead $lead) { $this->current_lead = $lead; }

	/**
	* @return double a value between 0 and 1 that represents the
	* rate of success this salesperson has with deals.
	*/
	protected abstract function success_rate();

	protected function can_take_lead(Lead $lead) {
		return $this->current_lead === null;
	}

    /**
     * @param Lead $lead
     * @param Salesperson $winner_so_far
     * @return SalesPerson
     */
    public function get_best_sales_rep(Lead $lead, Salesperson $winner_so_far = null)
	{
		if($this->can_take_lead($lead) && ($winner_so_far === null || $this->risk($lead) < $winner_so_far->risk($lead)))
            $winner_so_far = $this;

        if($this->left !== null)
            $winner_so_far = $this->left->get_best_sales_rep($lead, $winner_so_far);
        if($this->right !== null)
            $winner_so_far = $this->right->get_best_sales_rep($lead, $winner_so_far);

        return $winner_so_far;
	}

	/**
	* Sums the total risk incurred by this sales rep and the reps below.
	* @return float - the total risk incurred.
	*/
	public function total_risk_incurred()
	{
		$total = 0.0;
		if ($this->current_lead)
		{
			$total += $this->risk($this->current_lead);
		}
		if ($this->left)
		{
			$total += $this->left->total_risk_incurred();
		}
		if ($this->right)
		{
			$total += $this->right->total_risk_incurred();
		}
		return $total;
	}

	/**
     * @param lead
	 * @return float - the risk that the company takes on given
	 * the success_rate() of the Salesperson
	*/
	public function risk(Lead $lead) {
		return $lead->value() * (1 - $this->success_rate());
	}

    /**
     * @return String
     */
    public function __toString() {
        $s = "(" . $this->name . "|" . get_class($this) . "|" . $this->success_rate() . " ";
        if($this->left !== null) {
            $s = $s . $this->left->__toString() . " ";
        }
        if($this->right !== null) {
            $s = $s . $this->right->__toString() . " ";
        }
        $s = $s .  ")";
        return $s;
    }
}

class Sociopath extends Salesperson
{
    public function __construct($name)
    {
        parent::__construct($name);
    }

	public function success_rate()
	{
		return 0.85;
	}

    protected function can_take_lead(Lead $lead) {
        return parent::can_take_lead($lead) && $lead->value() >= 1000000;
    }
}

class Clueless extends Salesperson
{
    public function __construct($name)
    {
        parent::__construct($name);
    }

	public function success_rate()
	{
        if($this->parent !== null && is_a($this->parent, "Sociopath"))
            return 0.65;
        return 0.45;
	}
}

class Loser extends Salesperson
{
    public function __construct($name)
    {
        parent::__construct($name);
    }

	public function success_rate()
	{
        if($this->parent !== null && is_a($this->parent, "Loser"))
            return $this->parent->success_rate() / 2.;
        return 0.02;
	}
}

/**
* An object to represent sales leads (as in, deals that have yet to come in, not
* a salesperson's manager). Gives the name and $ value of the lead.
*/
class Lead
{
	private $name;
	private $value;

	public function __construct($name, $value)
	{
		$this->name = $name;
		$this->value = $value;
	}

	public function value()
	{
		return $this->value;
	}

    public function __toString() {
        return $this->name . " Value = " . $this->value;
    }
}
