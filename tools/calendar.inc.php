<?php
date_default_timezone_set( "UTC" );

/*
 * TODO : CalendarPeriod->GetFlat : TAKE DAYS IN ACCOUNT
 */
function date2Display( $date )
{
    return date2DisplayEx( $date, HLib( "LocaleInfo" )->GetLocale() );
}

function date2DisplayEx( $date, $locale )
{
    if( $locale == "fr" )
    {
        date_parse_ex( substr( $date, 0, 10 ), $year, $month, $day );

        if( $month > 0 && $month <= 12 )
            $monthName = i18n( "MonthNames", "fr", $month );
        else
            $monthName = "-";

        $str = ((integer) $day) . " " . $monthName . " $year";

        return $str;
    }

    return date( "F j, Y", strtotime( substr( $date, 0, 10 ) ) );
}

define( "TIME_BEGIN", '1970-01-01' );
define( "TIME_END", '2038-01-10' ); // the 19th jan 2038 is the last day that strtotime can handle...
function date_add_day( $date, $nbDays )
{
    // hr min sec month day year
    // return mktime( 1, 0, 0, 1, 2, 1970 );

    // 86400 is number of seconds in a day
    $time = strtotime( $date ) + 86400 * $nbDays;
    if( $time < 0 )
        $time = 0;
    $res = date( 'Y-m-d', $time );

    return $res;
}

function datetime_add_day( $date, $nbDays )
{
    // hr min sec month day year
    // return mktime( 1, 0, 0, 1, 2, 1970 );

    // 86400 is number of seconds in a day
    $time = strtotime( $date ) + 86400 * $nbDays;
    if( $time < 0 )
        $time = 0;
    $res = date( 'Y-m-d H:i:s', $time );

    return $res;
}

function date_interval_days( $from, $to )
{
    return 1 + ((strtotime( $to ) - strtotime( $from )) / 86400);
}

// returns year, month and date in human format (beginning at 1, not 0)
function date_parse_ex( $date, &$year, &$month, &$day )
{
    $year = substr( $date, 0, 4 );
    $month = substr( $date, 5, 2 );
    $day = substr( $date, 8, 2 );
}

function intdiv_ex( $q, $d )
{
    $reste = $q % $d;
    $res = ($q - $reste) / $d;

    return $res;
}

function date_get_day( $date )
{
    date_parse_ex( $date, $year, $month, $day );

    // Algorithme de Mike Keith
    $y = $year; // + 1900;
    $m = $month; // + 1;
    $d = $day;

    $z = ($m < 3) ? $y - 1 : $y;
    $r = ($m < 3) ? 0 : 2;

    return (intdiv_ex( 23 * $m, 9 ) + $d + 4 + $y + intdiv_ex( $z, 4 ) - intdiv_ex( $z, 100 ) + intdiv_ex( $z, 400 ) - $r) % 7;
}

function now()
{
    return date( 'Y-m-d', strtotime( 'now' ) );
}

function today()
{
    return date( 'Y-m-d', strtotime( 'now' ) );
}

function nowtime()
{
    return date( "Y-m-d H:i:s", strtotime( 'now' ) );
}

function SliceByYear( $periodExpr )
{
    $cal = new Calendar();
    $tree = $cal->Parse( $periodExpr );

    $from = null;
    $to = null;
    $cal->GetBoundaries( $tree, $from, $to );

    date_parse_ex( $from, $startYear, $m, $d );
    date_parse_ex( $to, $endYear, $m, $d );

    $periods = array();
    for( $year = $startYear; $year <= $endYear; $year++ )
        $periods[] = array(
            $year,
            "[$year-01-01;$year-12-31] $periodExpr &" );

    return $periods;
}

function &getSub( &$array, $key )
{
    $v = &$array[$key];

    return $v;
}

class RefHolder
{
    var $ref;

    function __construct( &$ref )
    {
        $this->ref = &$ref;
    }
}

function &pop( &$arr )
{
    $v = &$arr[count( $arr ) - 1];
    array_pop( $arr );

    return $v;
}

function push( &$arr, &$elem )
{
    $arr[] = $elem;
}

$treeCache = array();
$flatCache = array();

class Calendar
{
    function Parse( $expression )
    {
        $tree = $this->_Parse( $expression );

        return $tree;
    }

    function ParseParam( $expression, $dateParam )
    {
        // calculates the day of week from the given date
        $dayParam = Date( 'w', strtotime( $dateParam ) );

        $value = $this->_ParseParam( $expression, $dateParam, $dayParam );

        return $value;
    }

    // usually a period expression expresses a period of days,
    // like when humans are speaking to each others.
    // when we work on night periods, this would mean speaking in term of :
    // "les nuits du 2013-01-02 au 2013-01-05"
    // since our algorithms need sometime to work with periods with the semantic
    // "la nuit du 2013-01-02 jusqu'à la nuit du 2013-01-04 inclus"
    // this method is here to make the transposition...
    function TransformToNightsSpeaking( $expression )
    {
        $tree = $this->Parse( $expression );
        $trim = $this->GetFlat( $tree );
        $trim->TrimPeriods();
        $nightSpealingExpression = $trim->GetExpression();

        return $nightSpealingExpression;
    }

    // this is the opposite transformation of the previous function
    function TransformToDaysSpeaking( $expression )
    {
        $tree = $this->Parse( $expression );
        $trim = $this->GetFlat( $tree );

        // be careful : if this period is equivalent to "never", we can't get things done, we miss information...
        if( $trim->GetNbDays() == 0 )
            throw new BadMethodCallException( "", 42, null );

        $trim->UntrimPeriods();
        $daySpeakingExpression = $trim->GetExpression();

        return $daySpeakingExpression;
    }

    function IsEmpty( $tree )
    {
        return $this->GetFlat( $tree )->IsEmpty();
    }

    // returns the number of days contained in this period
    function GetNbDays( $tree )
    {
        return $this->GetFlat( $tree )->GetNbDays();
    }

    function GetNbNights( $tree )
    {
        return $this->GetFlat( $tree )->GetNbNights();
    }

    function GetBoundaries( $tree, &$from, &$to )
    {
        return $this->_GetBoundaries( $tree, $from, $to );
    }

    // takes as a parameter an array of period expressions and their initial values
    // returns an associative calendar that is the merge of all these
    // $addFunction is a callback that can combine two period values
    function MakeUpCalendarPeriodAssociative( $periodsAndValues, $addFunction )
    {
        $nbPeriod = count( $periodsAndValues );
        if( $nbPeriod == 0 )
            return new CalendarPeriodAssociative();

        // get the first non empty period
        $res = null;
        $first = 0;
        do
        {
            $firstPeriodAndValue = $periodsAndValues[$first];
            $tree = $this->Parse( $firstPeriodAndValue[0] );
            if( $this->GetNbDays( $tree ) == 0 )
            {
                // echo "IGNORING F PERIOD $first (" . $firstPeriodAndValue[0] . ") BECAUSE EMPTY<br/>";
                $first++;
                if( $first >= $nbPeriod )
                    return new CalendarPeriodAssociative();
                continue;
            }

            $flat = $this->GetFlat( $tree );
            $res = new CalendarPeriodAssociative();
            $res->Init( $flat, $firstPeriodAndValue[1] );

            break;
        } while( true );

        if( $res == null )
            return null; // no non-empty period

        for( $i = $first + 1; $i < $nbPeriod; $i++ )
        {
            $tree = $this->Parse( $periodsAndValues[$i][0] );
            if( $this->GetNbDays( $tree ) == 0 )
            {
                // echo "IGNORING N PERIOD $i (" . $periodsAndValues[$i][0] . ") BECAUSE EMPTY<br/>";
                continue;
            }

            $flat = $this->GetFlat( $tree );
            $assoc = new CalendarPeriodAssociative();
            $assoc->Init( $flat, $periodsAndValues[$i][1] );

            if( is_null( $res->Add( $assoc, $addFunction ) ) )
                return null;
        }

        return $res;
    }

    private function _hasDaySpec( $tree )
    {
        switch( $tree['t_type'] )
        {
            case 'a':
                return false;
            case 'n':
                return false;
            case 'd':
                return true;
            case 'p':
                return false;
            case '~':
                return $this->_hasDaySpec( $tree['t_op'] );
            case '&':
                return $this->_hasDaySpec( $tree['t_op_left'] ) || $this->_hasDaySpec( $tree['t_op_right'] );
            case '|':
                return $this->_hasDaySpec( $tree['t_op_left'] ) || $this->_hasDaySpec( $tree['t_op_right'] );
        }
    }

    function GetBeautiful( $tree )
    {
        return $this->GetBeautifulEx( $tree, HLib( "LocaleInfo" )->GetLocale() );
    }

    function GetBeautifulEx( $tree, $locale )
    {
        // should one day find something better !
        if( $this->_hasDaySpec( $tree ) )
        {
            return $this->_GetBeautiful( $tree, $locale );
        }
        else
        {
            $p = $this->GetFlat( $tree );
            $beauty = $p->GetBeautifulEx( $locale );
            if( $beauty == null )
                return "ERROR";

            return $beauty;
        }
    }

    function _GetBeautiful( $tree, $locale )
    {
        switch( $tree['t_type'] )
        {
            case 'a':
                return i18n( 'Always', $locale );
            case 'n':
                return i18n( 'Never', $locale );
            case 'd':
                return i18n( "WeekDays", $locale, $tree['t_val'] );
            case 'p':
                return i18n( "DateRange", $locale, date2DisplayEx( $tree['t_from'], $locale ), date2DisplayEx( $tree['t_to'], $locale ) );
            case '~':
                if( $tree['t_op']['t_type'] == 'a' )
                    return i18n( 'Never', $locale );

                return i18n( 'Not', $locale ) . ' ' . $this->_GetBeautiful( $tree['t_op'], $locale );
            case '&':
                return '( ' . $this->GetBeautifulEx( $tree['t_op_left'], $locale ) . ' ' . i18n( 'and', $locale ) . ' ' . $this->GetBeautifulEx( $tree['t_op_right'], $locale ) . ' )';
            case '|':
                return '( ' . $this->GetBeautifulEx( $tree['t_op_left'], $locale ) . ' ' . i18n( 'or', $locale ) . ' ' . $this->GetBeautifulEx( $tree['t_op_right'], $locale ) . ' )';
        }
    }

    function GetFlat( $tree )
    {
        $flat = $this->GetFlatInternal( $tree );

        return $flat;
    }

    function GetFlatInternal( $tree )
    {
        global $flatCache;

// 		if( isset($tree["_expression_"]) && isset($flatCache[$tree["_expression_"]]) )
// 			return $flatCache[$tree["_expression_"]];

        // on commence par la racine
        $stack = array();
        array_push( $stack, new RefHolder( $tree ) );

        $count = 50000;
        while( (count( $stack ) > 0) && ($count-- > 0) )
        {
            $nodeRef = array_pop( $stack );

            switch( $nodeRef->ref['t_type'] )
            {
                case 'a':
                    $p = new CalendarPeriod();
                    $p->Init( TIME_BEGIN, TIME_END );
                    $nodeRef->ref['flat'] = $p;
                    break;
                case 'n':
                    $p = new CalendarPeriod();
                    $p->Init();
                    $nodeRef->ref['flat'] = $p;
                    break;
                case 'd':
                    $p = new CalendarPeriod();
                    $p->InitWeekDays( $nodeRef->ref['t_val'] );
                    $nodeRef->ref['flat'] = $p;
                    break;
                case 'p':
                    $p = new CalendarPeriod();
                    $p->Init( $nodeRef->ref['t_from'], $nodeRef->ref['t_to'] );
                    $nodeRef->ref['flat'] = $p;
                    break;

                case '~':
                    if( array_key_exists( 'flat', $nodeRef->ref['t_op'] ) )
                    {
                        $nodeRef->ref['flat'] = $nodeRef->ref['t_op']['flat'];
                        $nodeRef->ref['flat']->Not();
                    }
                    else
                    {
                        array_push( $stack, $nodeRef );
                        array_push( $stack, new RefHolder( $nodeRef->ref['t_op'] ) );
                    }
                    break;

                case '&':
                case '|':
                    $leftProcessed = array_key_exists( 'flat', $nodeRef->ref['t_op_left'] );
                    $rightProcessed = array_key_exists( 'flat', $nodeRef->ref['t_op_right'] );

                    if( isset($nodeRef->ref["PARENT_HAS_CHILD_VISITED"]) )
                    {
                        if( (!$leftProcessed) || (!$rightProcessed) )
                        {
                            echo "ERROR : PARENT HAS BEEN PROCESSED IN REALITY<br/>";
                            Dump( $nodeRef->ref );

                            return null;
                        }
                    }

                    if( $leftProcessed && $rightProcessed )
                    {
                        // si les deux fils ont ete visités, on peut calculer la valeur
                        $nodeRef->ref['flat'] = $nodeRef->ref['t_op_left']['flat'];
                        if( $nodeRef->ref['t_type'] == '&' )
                            $nodeRef->ref['flat']->Intersect( $nodeRef->ref['t_op_right']['flat'] );
                        else if( $nodeRef->ref['t_type'] == '|' )
                            $nodeRef->ref['flat']->Add( $nodeRef->ref['t_op_right']['flat'] );
                    }
                    else
                    {
                        // sinon, visiter les fils non visités d'abord et garder le parent pour plus tard
                        array_push( $stack, $nodeRef );
                        if( !$leftProcessed )
                            array_push( $stack, new RefHolder( $nodeRef->ref['t_op_left'] ) );
                        if( !$rightProcessed )
                            array_push( $stack, new RefHolder( $nodeRef->ref['t_op_right'] ) );
                    }
                    break;
            }
        }

        $res = $nodeRef->ref['flat'];

// 		if( isset($tree["_expression_"]) )
// 			$flatCache[$tree["_expression_"]] = $res;

        return $res;
    }

    private function _GetBoundaries( $tree, &$from, &$to )
    {
        $flat = $this->GetFlatInternal( $tree );
        if( $flat == null )
        {
            echo "ERROR : flat is null with tree :";
            Dump( $tree );
        }

        return $flat->GetBoundaries( $from, $to );
    }

    private function _Parse( $expression )
    {
        global $treeCache;

        //if( $treeCache!=null && isset($treeCache[$expression]) )
        //	return $treeCache[$expression];

        $pos = 0;

        $stack = array();

        while( $token = $this->_NextToken( $expression, $pos ) )
        {
            // echo "token : " . $token['t_type'] . "<br/>";
            switch( $token['t_type'] )
            {
                case 'a':
                    array_push( $stack, $token );
                    break;
                case 'n':
                    array_push( $stack, $token );
                    break;
                case 'd':
                    array_push( $stack, $token );
                    break;
                case 'p':
                    array_push( $stack, $token );
                    break;
                case '~':
                    $operand = array_pop( $stack );
                    array_push( $stack, array(
                        't_type' => '~',
                        't_op' => $operand ) );
                    break;
                case '&':
                    $operandR = array_pop( $stack );
                    $operandL = array_pop( $stack );
                    array_push( $stack, array(
                        't_type' => '&',
                        't_op_left' => $operandL,
                        't_op_right' => $operandR ) );
                    break;
                case '|':
                    $operandR = array_pop( $stack );
                    $operandL = array_pop( $stack );
                    array_push( $stack, array(
                        't_type' => '|',
                        't_op_left' => $operandL,
                        't_op_right' => $operandR ) );
                    break;
            }
        }

        if( count( $stack ) != 1 )
            return null;

        $treeCache[$expression] = $stack[0];
        $treeCache[$expression]["_expression_"] = $expression;

        return $stack[0];
    }

    private function _ParseParam( $expression, $dateParam, $dayParam )
    {
        $pos = 0;

        $stack = array();

        while( $token = $this->_NextToken( $expression, $pos ) )
        {
            // echo "token : " . $token['t_type'] . "<br/>";
            switch( $token['t_type'] )
            {
                case 'a':
                    array_push( $stack, true );
                    break;
                case 'n':
                    array_push( $stack, false );
                    break;
                case 'd':
                    if( $token['t_val'] == $dayParam )
                        array_push( $stack, true );
                    else
                        array_push( $stack, false );
                    break;
                case 'p':
                    if( ($token['t_from'] <= $dateParam) && ($token['t_to'] >= $dateParam) )
                        array_push( $stack, true );
                    else
                        array_push( $stack, false );
                    break;
                case '~':
                    array_push( $stack, !array_pop( $stack ) );
                    break;
                case '&':
                    $operandR = array_pop( $stack );
                    $operandL = array_pop( $stack );
                    array_push( $stack, $operandL and $operandR );
                    break;
                case '|':
                    $operandR = array_pop( $stack );
                    $operandL = array_pop( $stack );
                    array_push( $stack, $operandL or $operandR );
                    break;
            }
        }

        if( count( $stack ) != 1 )
            return null;

        return $stack[0];
    }

    // parse a date in the format yyyy-mm-dd
    // return null if error
    function GetDate( &$text, &$pos )
    {
        if( $text[4 + $pos] != '-' || $text[7 + $pos] != '-' )
            return null;

        $date = substr( $text, $pos, 10 );
        $pos += 10;

        return $date;
    }

    // parse a day number (0=sunday)
    // return null if error
    function GetDay( &$text, &$pos )
    {
        $day = $text[$pos];
        $pos++;

        return $day;
    }

    // returns the next token and increments the position
    // returns null when no more token to come
    private function _NextToken( &$text, &$pos )
    {
        $len = strlen( $text );

        // skip whitespaces
        while( ($pos < $len) && ($text[$pos] == ' ') )
            $pos++;

        // end of text...
        if( $pos >= $len )
            return null;

        $token = null;

        if( $text[$pos] == '[' )
        {
            $pos++; // pass the '['

            // get date FROM
            $from = $this->GetDate( $text, $pos );
            if( $from == null )
            {
                // date not found : finished...
                $pos = $len;

                return null;
            }

            // skip whitespaces
            while( ($pos < $len) && ($text[$pos] == ' ') )
                $pos++;

            // check for ;
            if( $text[$pos] != ';' )
            {
                // error
                $pos = $len;

                return null;
            }
            $pos++;

            // skip whitespaces
            while( ($pos < $len) && ($text[$pos] == ' ') )
                $pos++;

            // get date TO
            $to = $this->GetDate( $text, $pos );
            if( $to == null )
            {
                // date not found : finished...
                $pos = $len;

                return null;
            }

            // skip whitespaces
            while( ($pos < $len) && ($text[$pos] == ' ') )
                $pos++;

            // check for ]
            if( $text[$pos] != ']' )
            {
                // error
                $pos = $len;

                return null;
            }
            $pos++;

            // return the period token
            $token = array(
                't_type' => 'p',
                't_from' => $from,
                't_to' => $to );

            return $token;
        }

        if( $text[$pos] == 'd' )
        {
            $pos++; // pass the 'd'

            // get number
            $day = $this->GetDay( $text, $pos );
            if( $day == null )
            {
                // day not found : finished...
                $pos = $len;

                return null;
            }

            // return day token
            $token = array(
                't_type' => 'd',
                't_val' => $day );

            return $token;
        }

        // test for operator token
        if( ($text[$pos] == '~') || ($text[$pos] == '&') || ($text[$pos] == '|') )
        {
            $token = array(
                't_type' => $text[$pos] );
            $pos++;

            return $token;
        }

        // test for the 'always' token
        if( $text[$pos] == 'a' )
        {
            $token = array(
                't_type' => 'a' );
            $pos++;

            return $token;
        }

        // test for the 'never' token
        if( $text[$pos] == 'n' )
        {
            $token = array(
                't_type' => 'n' );
            $pos++;

            return $token;
        }

        // last try a date only, which is shortcut for [from;to] with from and to equal...
        $date = $this->GetDate( $text, $pos );
        if( $date == null )
        {
            // date not found : finished...
            $pos = $len;

            return null;
        }
        $token = array(
            't_type' => 'p',
            't_from' => $date,
            't_to' => $date );

        return $token;
    }
}

class CalendarPeriod
{
    // list of untouching periods in chronological order
    var $periods = null;

    // list of days this period is available
    // if in this mode, it is implied that its boudaries are TIME_BEGIN to TIME_END
    var $days = null;

    // init
    function Init( $from = null, $to = null )
    {
        $this->periods = array();
        if( ($from != null) && ($to != null) )
            $this->periods[] = array( $from, $to );
    }

    // init as a week days unresolved period
    function InitWeekDays( $day )
    {
        $this->days = array();
        for( $i = 0; $i < 7; $i++ )
            $this->days[$i] = 0;
        $this->days[$day] = 1;
    }

    /** Get period's days as a list */
    public function GetDays()
    {
        // unresolved periods ?
        //$this->Resolve(TIME_BEGIN, TIME_END);

        $res = array();

        foreach( $this->periods as $period )
        {
            // TODO
            for( $date = $period[0]; $date <= $period[1]; $date = date_add_day( $date, 1 ) )
                $res[] = $date;
        }

        return $res;
    }

    // print string
    function Out()
    {
        if( is_null( $this->periods ) )
        {
            // means we are in unresolved week days mode
            return "Unresolved days : " . implode( ",", $this->days );
        }

        $out = "";
        foreach( $this->periods as $period )
            $out .= '[' . $period[0] . '->' . $period[1] . ']<br/>';

        return $out;
    }

    function GetBeautiful()
    {
        return $this->GetBeautifulEx( HLib( "LocaleInfo" )->GetLocale() );
    }

    function GetBeautifulEx( $locale )
    {
        if( is_null( $this->periods ) )
            return "Unresolved days : " . implode( ",", $this->days );

        if( count( $this->periods ) == 0 )
            return i18n( 'Never', $locale );

        if( (count( $this->periods ) == 1) && ($this->periods[0][0] == TIME_BEGIN) && ($this->periods[0][1] == TIME_END) )
            return i18n( 'Always', $locale );

        $expr = '';

        for( $i = 0; $i < count( $this->periods ); $i++ )
        {
            if( $this->periods[$i][0] == $this->periods[$i][1] )
                $expr .= date2DisplayEx( $this->periods[$i][0], $locale );
            else
            {
                if( $this->periods[$i][0] == TIME_BEGIN )
                    $expr .= i18n( 'until', $locale ) . ' ' . date2DisplayEx( $this->periods[$i][1], $locale );
                else if( $this->periods[$i][1] == TIME_END )
                    $expr .= i18n( 'from', $locale ) . ' ' . date2DisplayEx( $this->periods[$i][0], $locale );
                else
                    $expr .= i18n( 'DateRange', $locale, date2DisplayEx( $this->periods[$i][0], $locale ), date2DisplayEx( $this->periods[$i][1], $locale ) );
            }

            if( $i < (count( $this->periods ) - 1) )
                $expr = $expr . ', ';
        }

        return $expr;
    }

    function GetExpression()
    {
        if( is_null( $this->periods ) )
        {
            $expr = "";
            $nb = 0;
            foreach( $this->days as $day => $value )
            {
                if( $value == 0 )
                    continue;
                $expr .= " d$value";
                $nb++;
            }
            for( $i = 0; $i < $nb - 1; $i++ )
                $expr .= " |";

            return $expr;
        }

        if( count( $this->periods ) == 0 )
            return 'n';

        if( (count( $this->periods ) == 1) && ($this->periods[0][0] == TIME_BEGIN) && ($this->periods[0][1] == TIME_END) )
            return 'a';

        $expr = '';

        for( $i = 0; $i < count( $this->periods ); $i++ )
        {
            $expr .= '[' . $this->periods[$i][0] . ';' . $this->periods[$i][1] . '] ';
            if( $i > 0 )
                $expr = $expr . ' |';
        }

        return $expr;
    }

    // returns true if the submitted date is included in the list of periods, false if not
    function IsInside( $date )
    {
        if( $this > periods == null )
        {
            if( $this->days[date_get_day( $date )] > 0 )
                return true;

            return false;
        }

        foreach( $this->periods as $period )
        {
            if( $period[0] > $date )
                return false;
            if( ($period[0] <= $date) && ($period[1] >= $date) )
                return true;
        }

        return false;
    }

    // returns true if the submitted period is completely covered within the list of periods, false if not
    function IsContained( $from, $to )
    {
        if( is_null( $this->periods ) )
        {
            echo "IsContained() TO BE IMPLEMENTED FLLKJ :: {{ } ''<br/>";
        }

        foreach( $this->periods as $period )
        {
            if( $period[0] > $from )
                return false;

            if( ($from >= $period[0]) && ($to <= $period[1]) )
                return true;
        }

        return false;
    }

    function IsEmpty()
    {
        return count( $this->periods ) == 0;
    }

    function GetNbDays()
    {
        if( is_null( $this->periods ) )
        {
            echo "IsContained() TO BE IMPLEMENTED FLLKJ :: {{ } ''<br/>";
        }

        $nbDays = 0;

        foreach( $this->periods as $period )
        {
            // number of seconds
            $nbSec = (strtotime( $period[1] ) - strtotime( $period[0] ));

            // add one because the period description is inclusive
            $nbDays += 1 + $nbSec / 86400;
        }

        return $nbDays;
    }

    function GetNbNights()
    {
        if( is_null( $this->periods ) )
        {
            echo "IsContained() TO BE IMPLEMENTED FLLKJ :: {{ } ''<br/>";
        }

        $nbNights = 0;

        foreach( $this->periods as $period )
        {
            // number of seconds
            $nbSec = (strtotime( $period[1] ) - strtotime( $period[0] ));

            $nbNights += $nbSec / 86400;
        }

        return $nbNights;
    }

    function GetBoundaries( &$from, &$to )
    {
        if( is_null( $this->periods ) )
        {
            $from = TIME_BEGIN;
            $to = TIME_END;

            return 0;
        }

        if( count( $this->periods ) == 0 )
        {
            $from = TIME_BEGIN;
            $to = TIME_BEGIN; // change made on the 2011-02-10, hope it doesn't break anyting...
            // $to = TIME_END;
            return 0;
        }

        $from = $this->periods[0][0];
        $to = $this->periods[count( $this->periods ) - 1][1];

        return 1;
    }

    function GetLeastBoundaries( &$from, &$to )
    {
        if( is_null( $this->periods ) )
        {
            $from = TIME_BEGIN;
            $to = TIME_END;

            return null;
        }

        if( count( $this->periods ) == 0 )
        {
            $from = TIME_BEGIN;
            $to = TIME_END;

            return null;
        }

        $from = $this->periods[0][0];
        $to = $this->periods[0][1];

        return 1;
    }

    // OR combination
    function Add( CalendarPeriod $period )
    {
        if( (!is_null( $this->days )) && (!is_null( $period->days )) )
        {
            for( $i = 0; $i < 7; $i++ )
                $this->days[$i] = ($this->days[$i] + $period->days[$i]) >= 1 ? 1 : 0;

            return;
        }

        // if one of the two operands is unresolved, it's a good time to resolve it now
        if( (!is_null( $this->days )) || (!is_null( $period->days )) )
        {
            if( is_null( $this->days ) )
            {
                $periods = $this->periods;
                $toResolve = $period;
            }
            else
            {
                $periods = $period->periods;
                $toResolve = $this;
            }

            $from = $periods[0][0];
            $to = $periods[count( $periods ) - 1][1];

            $toResolve->Resolve( $from, $to );
        }

        // combine les deux tableaux dans ordre croissant
        $combined = $this->_Combine( $this->periods, $period->periods );

        // merge overlapping periods
        $result = $this->_Merge( $combined );

        $this->periods = $result;
    }

    // AND operator
    function Intersect( CalendarPeriod $period )
    {
        if( (!is_null( $this->days )) && (!is_null( $period->days )) )
        {
            for( $i = 0; $i < 7; $i++ )
                $this->days[$i] = ($this->days[$i] + $period->days[$i]) >= 2 ? 1 : 0;

            return;
        }

        // if one of the two operands is unresolved, it's a good time to resolve it now
        if( (!is_null( $this->days )) || (!is_null( $period->days )) )
        {
            if( is_null( $this->days ) )
            {
                $periods = $this->periods;
                $toResolve = $period;
            }
            else
            {
                $periods = $period->periods;
                $toResolve = $this;
            }

            $from = $periods[0][0];
            $to = $periods[count( $periods ) - 1][1];

            $toResolve->Resolve( $from, $to );
        }

        // echo "Intersect " . $this->Out() . " with " . $period->Out() . "<br/>";

        // intersect the two period list
        $result = $this->_Intersect( $this->periods, $period->periods );

        $this->periods = $result;
    }

    /**
     * Trim last day of each period
     * Used for nights accommodations
     *
     * @author Laurent
     */
    function TrimPeriods()
    {
        foreach( $this->periods as $i => $period )
        {
            if( $this->periods[$i][0] == $this->periods[$i][1] ) // One single day, delete period
            {
                unset($this->periods[$i]);
                continue;
            }

            // Trim
            $this->periods[$i][1] = date_add_day( $this->periods[$i][1], -1 );
        }
    }

    function UntrimPeriods()
    {
        for( $i = 0; $i < count( $this->periods ); $i++ )
        {
            $this->periods[$i][1] = date_add_day( $this->periods[$i][1], 1 );
        }

        $this->periods = $this->_Merge( $this->periods );
    }

    // starting from an unresolved periods, we build a resolved one, based on from and to parameters
    function Resolve( $from, $to )
    {
        if( !is_null( $this->periods ) )
        {
            // call on an already resolved CalendarPeriod
            echo "LJLJKZHL KJH ELF B.EB EKJGF EFJBH EKLFJHL JGH <{{ : ' } <br/>";

            return;
        }

        // echo "Resolving from $from to $to " . implode( ".", $this->days ) . "<br/>";

        // if all days are selected, make a whole period
        // build the micro periods
        $nb = 0;
        for( $i = 0; $i < 7; $i++ )
            $nb += $this->days[$i];
        if( $nb == 7 )
        {
            $this->periods = array(
                array(
                    $from,
                    $to ) );

            return;
        }
        else if( $nb == 0 )
        {
            $this->periods = array();

            return;
        }

        // echo "Continuing<br/>";
        $fromDay = date_get_day( $from );

        // we have at least one gap
        $groups = array();
        $curGroup = null;
        for( $i = $fromDay; $i < $fromDay + 7; $i++ )
        {
            if( $this->days[$i % 7] > 0 )
            {
                if( is_null( $curGroup ) ) // no group created yet
                    $curGroup = array(
                        $i - $fromDay,
                        $i - $fromDay );
                else if( $curGroup[1] == $i - $fromDay - 1 ) // day jointed to current group
                    $curGroup[1] = $i - $fromDay;
                else // day disjointed from current group
                {
                    $groups[] = $curGroup;
                    $curGroup = array(
                        $i - $fromDay,
                        $i - $fromDay );
                }
            }
        }
        if( $curGroup != null )
            $groups[] = $curGroup;

        // Dump( $groups );

        // now generate the periods
        $msg = "Starts on $from, which day is a $fromDay<br/>";
        foreach( $groups as $group )
            $msg .= "Group : " . implode( " to ", $group ) . "<br/>";

        // echo "From day : $from : $fromDay <br/>";

        $firstOccurence = $from;

        // echo "First occurence : $firstOccurence<br/>";

        $this->days = null;
        $this->periods = array();
        while( $firstOccurence <= $to )
        {
            $msg .= "Occurence $firstOccurence<br/>";
            // day of $firstOccurence is always $fromDay
            foreach( $groups as $group )
            {
                $mpFrom = date_add_day( $firstOccurence, $group[0] );
                if( $mpFrom <= $to )
                {
                    $mpTo = date_add_day( $firstOccurence, $group[1] );
                    if( $mpTo > $to )
                        $mpTo = $to;

                    $msg .= "Adding $mpFrom, $mpTo<br/>";

                    $this->periods[] = array(
                        $mpFrom,
                        $mpTo );
                }
            }

            $firstOccurence = date_add_day( $firstOccurence, 7 );
        }

        // HLib("ServerState")->AddMessage( $msg );
    }

    // NOT operator
    function Not()
    {
        if( is_null( $this->periods ) )
        {
            for( $i = 0; $i < 7; $i++ )
                $this->days[$i] = $this->days[$i] > 0 ? 0 : 1;

            return;
        }

        $result = array();

        $curBegin = TIME_BEGIN;
        foreach( $this->periods as $period )
        {
            if( (date_add_day( $period[0], -1 )) >= $curBegin )
                $result[] = array(
                    $curBegin,
                    date_add_day( $period[0], -1 ) );
            $curBegin = date_add_day( $period[1], 1 );
        }

        if( TIME_END >= $curBegin )
            $result[] = array(
                $curBegin,
                TIME_END );

        $this->periods = $result;
    }

    // intersect two period arrays
    function _Intersect( $periods1, $periods2 )
    {
        $result = array();

        $count1 = count( $periods1 );
        $count2 = count( $periods2 );

        $i = 0;
        $j = 0;

        while( $i < $count1 && $j < $count2 )
        {
            // one of the periods begins after the end of the other
            if( $periods1[$i][0] > $periods2[$j][1] )
            { // period 1 begins after period 2 finishes => period2 is eliminated !
                $j++;
            }
            else if( $periods2[$j][0] > $periods1[$i][1] )
            { // period 2 begins after end of period 1 => period 1 is eliminated !
                $i++;
            }

            // after that test, we can assume there is a non-void intersection
            else
            {
                $result[] = array(
                    max( $periods1[$i][0], $periods2[$j][0] ),
                    min( $periods1[$i][1], $periods2[$j][1] ) );

                if( $periods1[$i][1] > $periods2[$j][1] )
                    $j++;
                else
                    $i++;
            }
        }

        return $result;
    }

    // combine two period arrays ordered by from date
    function _Combine( $periods1, $periods2 )
    {
        $result = array();

        $count1 = count( $periods1 );
        $count2 = count( $periods2 );

        $i = 0;
        $j = 0;
        while( $i < $count1 && $j < $count2 )
        {
            if( $periods1[$i][0] <= $periods2[$j][0] )
            {
                $result[] = $periods1[$i];
                $i++;
            }
            else
            {
                $result[] = $periods2[$j];
                $j++;
            }
        }
        while( $i < $count1 )
        {
            $result[] = $periods1[$i];
            $i++;
        }
        while( $j < $count2 )
        {
            $result[] = $periods2[$j];
            $j++;
        }

        return $result;
    }

    // merge, that is combine overlapping periods
    function _Merge( $periods )
    {
        $result = array();

        $count = count( $periods );
        if( $count == 0 )
            return $result;

        // copy the first period
        $result[] = $periods[0];

        // init
        $resIdx = 0;
        $i = 1;

        while( $i < $count )
        {
            if( $periods[$i][0] > date_add_day( $result[$resIdx][1], 1 ) )
            {
                // period is disjointed, so add it
                $result[] = $periods[$i];
                $resIdx++;
            }
            else
            {
                // period is jointed so merge
                $result[$resIdx][1] = max( $result[$resIdx][1], $periods[$i][1] );
            }
            $i++;
        }

        return $result;
    }
}

// associates periods with values
class CalendarPeriodAssociative
{
    // list of untouching periods in chronological order
    var $periods = array(); // 0:from, 1:to, 2:value

    // init from a CalendarPeriod with a value
    function Init( CalendarPeriod $period, $value )
    {
        $this->periods = $period->periods;
        foreach( $this->periods as $i => $p )
            $this->periods[$i][2] = $value;
    }

    // print string
    function Out()
    {
        $out = "";
        foreach( $this->periods as $period )
            $out .= '[' . $period[0] . '->' . $period[1] . '] => ' . $period[2] . '<br/>';

        return $out;
    }

    // print string
    function OutEx( $printer )
    {
        $out = "";
        foreach( $this->periods as $period )
            $out .= '[' . $period[0] . '->' . $period[1] . '] => ' . $printer( $period[2] ) . '<br/>';

        return $out;
    }

    // adding two associative periods
    function Add( CalendarPeriodAssociative $period, $addFunction )
    {
        // combine les deux tableaux dans ordre croissant
        $combined = $this->_Combine( $this->periods, $period->periods );

        // merge overlapping periods
        $result = $this->_Merge( $combined, $addFunction );
        if( is_null( $result ) )
            return null; // we should stop the MakeUpCalendarAssociative process

        $this->periods = $result;

        // to say we can continue...
        return true;
    }

    // returns the same but with no values, and with the periods merged
    function GetCalendarPeriod()
    {
        $periods = array();
        foreach( $this->periods as $p )
            $periods[] = array(
                $p[0],
                $p[1] );

        $res = new CalendarPeriod();
        // use merge to merge jointed periods...
        $res->periods = $res->_Merge( $periods );

        return $res;
    }

    // returns a CalendarPeriodAssociative objet corresponding to the periods where $testFunction returned true
    function Extract( $testFunction )
    {
        $periods = array();
        foreach( $this->periods as $test )
        {
            if( $testFunction( $test[2] ) == true )
            {
                $periods[] = $test;
            }
        }

        $res = new CalendarPeriodAssociative();
        $res->periods = $periods;

        return $res;
    }

    // returns a new object that is the current object trimmed by the given $calendarPeriod
    function Trim( $calendarPeriod )
    {
        $result = array();

        $count1 = count( $this->periods );
        $count2 = count( $calendarPeriod->periods );

        $i = 0;
        $j = 0;

        while( $i < $count1 && $j < $count2 )
        {
            // one of the periods begins after the end of the other
            if( $this->periods[$i][0] > $calendarPeriod->periods[$j][1] )
            { // period 1 begins after period 2 finishes => period2 is eliminated !
                $j++;
            }
            else if( $calendarPeriod->periods[$j][0] > $this->periods[$i][1] )
            { // period 2 begins after end of period 1 => period 1 is eliminated !
                $i++;
            }

            // after that test, we can assume there is a non-void intersection
            else
            {
                $result[] = array(
                    max( $this->periods[$i][0], $calendarPeriod->periods[$j][0] ),
                    min( $this->periods[$i][1], $calendarPeriod->periods[$j][1] ),
                    $this->periods[$i][2] );

                if( $this->periods[$i][1] > $calendarPeriod->periods[$j][1] )
                    $j++;
                else
                    $i++;
            }
        }

        $res = new CalendarPeriodAssociative();
        $res->periods = $result;

        return $res;
    }

    // combine two period arrays ordered by from date
    function _Combine( $periods1, $periods2 )
    {
        $result = array();

        $count1 = count( $periods1 );
        $count2 = count( $periods2 );

        $i = 0;
        $j = 0;
        while( $i < $count1 && $j < $count2 )
        {
            if( $periods1[$i][0] <= $periods2[$j][0] )
            {
                $result[] = $periods1[$i];
                $i++;
            }
            else
            {
                $result[] = $periods2[$j];
                $j++;
            }
        }
        while( $i < $count1 )
        {
            $result[] = $periods1[$i];
            $i++;
        }
        while( $j < $count2 )
        {
            $result[] = $periods2[$j];
            $j++;
        }

        return $result;
    }

    // merge, that is combine overlapping periods
    function _Merge( $periods, $addFunction )
    {
        $count = count( $periods );
        if( $count == 0 )
            return array();
        if( $count == 1 )
            return $periods;

        $result = array();

        while( count( $periods ) > 1 )
        {
            if( $periods[1][0] > $periods[0][1] )
            {
                // period is disjointed, so forget the first period, add it directly into the results
                $result[] = array_shift( $periods );
            }
            else
            {
                $toAdd = array();

                if( $periods[0][0] < $periods[1][0] )
                {
                    $created = array(
                        $periods[0][0],
                        date_add_day( $periods[1][0], -1 ),
                        $periods[0][2] );
                    $toAdd[] = $created;
                }

                if( $periods[1][1] < $periods[0][1] )
                {
                    $r = $addFunction( $periods[0][2], $periods[1][2] );
                    if( is_null( $r ) )
                        return null;

                    $toAdd[] = array(
                        $periods[1][0],
                        $periods[1][1],
                        $r );
                    $toAdd[] = array(
                        date_add_day( $periods[1][1], 1 ),
                        $periods[0][1],
                        $periods[0][2] );
                }
                else if( $periods[1][1] == $periods[0][1] )
                {
                    $r = $addFunction( $periods[0][2], $periods[1][2] );
                    if( is_null( $r ) )
                        return null;

                    $toAdd[] = array(
                        $periods[1][0],
                        $periods[1][1],
                        $r );
                }
                else // $periods[1][1] > $periods[0][1]
                {
                    $r = $addFunction( $periods[0][2], $periods[1][2] );
                    if( is_null( $r ) )
                        return null;

                    $toAdd[] = array(
                        $periods[1][0],
                        $periods[0][1],
                        $r );
                    $toAdd[] = array(
                        date_add_day( $periods[0][1], 1 ),
                        $periods[1][1],
                        $periods[1][2] );
                }

                // periods 0 and 1 should be replaced by the newly calculated $toAdd periods

                // remove periods 0 and 1
                array_shift( $periods );
                array_shift( $periods );

                $periods = $this->_Combine( $periods, $toAdd );
            }
        }

        if( count( $periods ) > 0 )
            $result[] = array_shift( $periods );

        return $result;
    }
}