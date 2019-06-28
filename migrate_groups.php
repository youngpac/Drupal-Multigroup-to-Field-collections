function migrate_group_fields(){
     /*
   * If there are any CCK Multigroup fields in the database left from Drupal 6,
   * convert them to Field Collection fields.
   */
  

    // Get a list of the Multigroup fields.
    $sql = "SELECT  type_name, group_name, label FROM d_content_group where group_type='multigroup' and group_name not in ('group_contractors_affiliation_')";
    $result_fields = db_query($sql);
    if ($result_fields) {
      // Report status.
      drupal_set_message(t('Converting Content Multigroup fields (part of CCK) from Drupal 6 to Field Collection fields...'));

      // Iterate through each multigroup.
      while ($group = $result_fields->fetchObject()) {
    drupal_set_message(t("we are here 1"));
        // Create the Field Collection field if it doesn't exist.
        try {
        $field_collection_name = 'b' . $group->group_name;
        $prior_field = field_read_field($field_collection_name, array('include_inactive' => TRUE));
        if (empty($prior_field)) {
          field_info_cache_clear();

          $definition_field = array(
            'field_name' => $field_collection_name,
            'type' => 'field_collection',
            'cardinality' => FIELD_CARDINALITY_UNLIMITED,
          );
        
          if (!field_info_field($definition_field)){
          field_create_field($definition_field);
          
        }
       
    }
        }catch (exception $e){
        print_r($e);
        }
     drupal_set_message(t("we are here 2"));
        // Create an instance by adding it to the proper content type.
     
        $definition_instance = array(
          'field_name' => $field_collection_name,
          'entity_type' => 'node',
          'bundle' => $group->type_name,
          'label' => $group->label,
          'widget' => array('type' => 'field_collection_embed'),
        );
          try {
        if(field_info_instance('node',$field_collection_name,$group->type_name)==null){
            field_create_instance($definition_instance);
        }
     }catch (exception $e){print_r($e);}
            drupal_set_message(t("we are here 3"));
         
        // Get the list of subfields.
        $sql = "SELECT field_name FROM d_content_group_fields WHERE type_name = :bundle AND group_name = :group ";
        $result_subfields = db_query($sql, array(
          ':bundle' => $group->type_name,
          ':group' => $group->group_name,
        ));
       
        

        drupal_set_message(t("we are here 4"));    
        $nodes = array();
        $subfields = array();
        if ($result_subfields) {
            
          // Iterate through each one.
     
          while ($subfield = $result_subfields->fetchObject()) {
        
            // Get the old instance settings to use in the new instance.
            $instance_old = field_info_instance(
              'node',
              $subfield->field_name,
              $group->type_name
            );
                drupal_set_message(t("we are here 5"));   
            // Add this subfield to the new Field Collection instance.
            $definition_instance = array(
              'field_name' => $subfield->field_name,
              'entity_type' => 'field_collection_item',
              'bundle' => $field_collection_name,
              'label' => $instance_old['label'],
              'description' => $instance_old['description'],
              'required' => $instance_old['required'],
              'settings' => $instance_old['settings'],
              'widget' => $instance_old['widget'],
              'display' => $instance_old['display'],
            );
            if(field_info_instance('field_collection_item',$subfield->field_name, $field_collection_name)==null){
            field_create_instance($definition_instance);
            }
            // Record the subfield name.
            $subfields[] = $subfield->field_name;
          }
       

          // Get all the nodes that have value in the multigroup by looking
          // for data in the first subfield.
          // TODO: Use db_query() instead as it's more efficient.  
                                foreach ($subfields as $subfield){
                                    //check the subfields kama ziko na data 
                                     //This check looks to see if there is data and then it picks the table with the maximum data. This table is the one that is used to populate the field Collection Groups.   
                                  $newtable = 'field_data_' . $subfield;
                                  //print_r($newtable);
                                  $query = db_select($newtable, 'table_alias');
                                   $query->addExpression('DISTINCT entity_id', 'nid');
                                    $query->addExpression('revision_id', 'vid');
                                    $results = $query->execute();
                                $nodes[] = $results->fetchAll();
                                            
                                    }
                   for ($i = 0; $i < count($nodes); ++$i) {
              
       /* if ($usetable <>'field_data_'){
          $query = db_select('field_data_'.$subfields[0]);
          // ->condition('entity_type', 'node');
           //->condition('bundle', $group->type_name);
          $query->addExpression('DISTINCT entity_id', 'nid');
          $query->addExpression('revision_id', 'vid');
          $nodes = $query->execute();
          }*/
    

          
       //dpq($query, $return = FALSE, $name = NULL);
          	drupal_set_message (t("we are here 6"));
            
// Construct a legacy multigroup for each node from individual fields.
          foreach ($nodes[$i] as $node) {
 
          	drupal_set_message (t("we are here 7"));
            $multigroup_data = array();
            foreach ($subfields as $field) {
              // TODO: Use db_query() instead as it's more efficient.
              $field_result = db_select('field_data_' . $field, 'field')
                ->fields('field')
                ->condition('entity_type', 'node')
                ->condition('entity_id', $node->nid)
                ->execute();
                 // dpq($field_query, $return = FALSE, $name = NULL);
              foreach ($field_result as $field_item) {
                $multigroup_data[$field_item->delta][$field] = $field_item;
				
              }
            }

	
            // Step through the reconstructed multigroups, now collections.
            foreach ($multigroup_data as $delta => $data) {
                //check if node already in the table to avoid multiple instances
                      
                        // Create entry in field_collection_item table.
              $field_collection_id = db_insert('field_collection_item')
                ->fields(array(
                  'field_name' => $field_collection_name,
                  'revision_id' => 0,
                ))
                ->execute();


              // Get the revision ID and update the above record with it.
              $revid = db_insert('field_collection_item_revision')
                ->fields(array('item_id' => $field_collection_id))
                ->execute();
              db_update('field_collection_item')
              ->fields(array('revision_id' => $revid))
              ->condition('item_id', $field_collection_id)
              ->execute();

             $checking = db_select('field_data_' . $field_collection_name,'field')
                                ->fields('field')
                                ->condition('entity_type', 'node')
                                ->condition('entity_id', $node->nid)
                                ->condition('revision_id', $node->vid)
                                 ->condition('language', LANGUAGE_NONE)
                                 ->condition('delta',$delta)
                                ->execute();
                  $checks =$checking->fetchAssoc();
                  if(!$checks){
                  drupal_set_message (t("we are in the bgroup_looper"));
              // Attach collection field data to the node.
              db_insert('field_data_' . $field_collection_name)
                ->fields(array(
                'entity_type' => 'node',
                  'bundle' => $group->type_name,
                  'entity_id' => $node->nid,
                  'revision_id' => $node->vid,
                  'language' => LANGUAGE_NONE,
                  'delta' => $delta,
                  $field_collection_name . '_value' => $field_collection_id,
                  $field_collection_name . '_revision_id' => $revid,
                ))
                ->execute();
	
              // Attach collection field revisions to the node.
              db_insert('field_revision_' . $field_collection_name)
                ->fields(array(
                  'entity_type' => 'node',
                  'bundle' => $group->type_name,
                  'entity_id' => $node->nid,
                  'revision_id' => $node->vid,
                  'language' => 'und',
                  'delta' => $delta,
                  $field_collection_name . '_value' => $field_collection_id,
                  $field_collection_name . '_revision_id' => $revid,
                ))
                ->execute();
				}
				
              // Go through all the fields in the multigroup.
              foreach ($data as $multigroup_field => $field_data) {

                // Reassign multigroup field data to the field-collection instance.
                db_update('field_data_' . $multigroup_field)
                  ->fields(array(
                    'entity_type' => 'field_collection_item',
                    'bundle' => $field_collection_name,
                    'entity_id' => $field_collection_id,
                    'language' => LANGUAGE_NONE,
                    'revision_id' => $revid,
                    'delta' => 0,
                  ))
                  ->condition('entity_type', 'node')
                  ->condition('entity_id', $node->nid)
                  ->condition('bundle', $group->type_name)
                  ->condition('delta', $delta)
                  ->execute();
 
// drupal_set_message(t('Reassigned data of .' .$multigroup_field));
 
                // Update the field revisions table.
                db_delete('field_revision_' . $multigroup_field)
                  ->condition('entity_type', 'node')
                  ->condition('entity_id', $node->nid)
                ->execute();
                $query = "INSERT INTO d_field_revision_$multigroup_field SELECT * FROM d_field_data_$multigroup_field WHERE entity_id = $field_collection_id AND entity_type = 'field_collection_item' AND bundle = '$field_collection_name'";
                db_query($query);
             
			 
			  }
            }
          }

          // Reset the cardinality of the converted fields to 1.
          // Multiple values are now handled by Field Collection.
          // This must be done after the migration, or we'll lose data.
          db_update('field_config')
            ->fields(array('cardinality' => 1))
            ->condition('field_name', $subfields)
            ->execute();
        drupal_set_message(t('Reset the cardinality '.$field." ".$field_collection_name." ". $multigroup_field ));
		}
      
	 }
	  }

      // Report status.
      drupal_set_message(t('Done! Once you have confirmed that all field data has been migrated, you may remove the Drupal 6 "content_group" and "content_group_fields" tables as well as the independent fields used in the generated collections; they should no longer be needed.'));
    }
    return "migration of multi fields complete";
  }

