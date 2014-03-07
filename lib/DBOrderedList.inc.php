<?php

class DBOrderedList
{
	var $QPath;
	var $table;
	var $groupField;
	var $positionField;

	public function __construct( $qpath, $table, $groupField, $positionField )
	{
		$this->QPath = $qpath;
		$this->table = $table;
		$this->groupField = $groupField;
		$this->positionField = $positionField;
	}

	public function getUpdateFunction()
	{
		$groupField = $this->groupField;
		return function( $qpath, $table, $idField, $id, $fieldName, $oldValue, $newValue )  use( $groupField )
		{
			if( $newValue < 0 )
				return;
				
			$movedRecord = $qpath->QueryOne( "$table [$idField=$id]" );
			if( $groupField == null )
				$groupValue = null;
			else
				$groupValue = $movedRecord["$table.$groupField"];

			// check newValue integrity
			if( $groupField == null )
				$entries = $qpath->QueryEx( "$table" );
			else
				$entries = $qpath->QueryEx( "$table [$groupField=$groupValue]" );
			if( $newValue >= $entries->GetNbRows() )
				$newValue = $entries->GetNbRows() - 1;

			if( $groupField == null )
				$groupingCondition = "";
			else
				$groupingCondition = "$groupField=$groupValue AND ";
			$qpath->UpdateRaw( $table, "($groupingCondition $fieldName>$oldValue)", array( $fieldName=>"$fieldName-1" ) );
			$qpath->UpdateRaw( $table, "($groupingCondition $fieldName>=$newValue)", array( $fieldName=>"$fieldName+1" ) );
			$qpath->Update( $table, $idField.'='.$id, array( $fieldName => $newValue ) );
		};
	}

	public function UpdatePosition( $recordId, $newPosition )
	{
		if( $newPosition < 0 )
			return;

		$groupField = $this->groupField;
		$positionField = $this->positionField;

		$table = $this->table;
		$idField = "id";
			
		$movedRecord = $this->QPath->QueryOne( "$table [$idField=$recordId]" );

		$oldPosition = $movedRecord["$table.$positionField"];

		if( $oldPosition == $newPosition )
			return;

		if( $groupField == null )
			$groupValue = null;
		else
			$groupValue = $movedRecord["$table.$groupField"];

		// check newPosition integrity
		if( $groupField == null )
			$entries = $this->QPath->QueryEx( "$table" );
		else
			$entries = $this->QPath->QueryEx( "$table [$groupField=$groupValue]" );
		if( $newPosition >= $entries->GetNbRows() )
			$newPosition = $entries->GetNbRows() - 1;

		if( $groupField == null )
			$groupingCondition = "";
		else
			$groupingCondition = "$groupField=$groupValue AND ";
		$this->QPath->UpdateRaw( $table, "($groupingCondition $positionField>$oldPosition)", array( $positionField=>"$positionField-1" ) );
		$this->QPath->UpdateRaw( $table, "($groupingCondition $positionField>=$newPosition)", array( $positionField=>"$positionField+1" ) );
		$this->QPath->Update( $table, $idField.'='.$recordId, array( $positionField => $newPosition ) );
	}

	public function Append( $fields )
	{
		if( $this->groupField!=null && (! isset( $fields[$this->groupField] )) )
			return null;

		if( $this->groupField == null )
			$res =  $this->QPath->QueryEx( $this->table );
		else
			$res =  $this->QPath->QueryEx( $this->table . " [".$this->groupField."=".$fields[$this->groupField]."]" );
		$position = $res->GetNbRows();

		$fields[$this->positionField] = $position;

		$id = $this->QPath->Insert( $this->table, $fields );

		return $id;
	}

	public function Delete( $condition )
	{
		$deleted = $this->QPath->QueryOne( $this->table . " [$condition]" );
		$pos = $deleted[ $this->table . "." . $this->positionField ];
		if( $this->groupField == null )
			$groupValue = null;
		else
			$groupValue = $deleted[ $this->table . "." . $this->groupField ];

		$res = $this->QPath->Delete( $this->table, $condition );

		if( $res >= 0 )
		{
			if( $this->groupField == null )
				$groupingCondition = "";
			else
				$groupingCondition = $this->groupField."=$groupValue AND";

			$this->QPath->UpdateRaw( $this->table, "($groupingCondition ".$this->positionField.">$pos)", array( $this->positionField=>($this->positionField."-1") ) );
		}

		return $res;
	}
}

?>