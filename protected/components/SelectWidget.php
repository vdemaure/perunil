<?php

class SelectWidget extends CWidget {

    public $model;
    public $selected = 'all';
    public $defaultlabel = "Tous";

    /**
     * Si vrai, affiche une entrée "NULL", utile pour supprimer une association.
     * @var bool 
     */
    public $showNull = false;
    public $ajax = false;
    public $column;      // Nom de la colonne, si non spécifié, c'est le nom de la table qui est utilisé.
    private $tbl_name;
    private $tbl_name_low;
    private $tbl_id;
    // Varables pour les formulaire d'édition uniquement
    public $frm_classname; // si true, le widget est utilisé dans un formulaire d'édition
    public $select_name = "";
    public $select_id; // #id du select, construit dans init()

    public function init() {
        $this->tbl_name = get_class($this->model);
        $this->tbl_name_low = strtolower($this->tbl_name);

        if (!isset($this->column)) {
            $this->column = $this->tbl_name_low;
        }

        $this->tbl_id = strtolower($this->tbl_name . "_id");
        
// Création du id du select
        if (empty($this->select_id)){
            $this->select_id = 'selectWidget' . $this->tbl_name . rand(101, 999);
        }
        
        if ($this->select_name == "") {
            $this->select_name = $this->tbl_name_low; // nom de la balise select et donc de l'entrée dans $_POST
        }
        if ($this->frm_classname) {
            $this->defaultlabel = '';
            // Si il s'agit d'un formulaire d'édition, on ajoute comme prefix le nom de la classe
            $this->select_name = $this->frm_classname . "[" . $this->tbl_name_low . "]";
        }
    }

    public function run() {
        if (!$this->ajax)
            echo "<div style=\"display: inline-block;\" id=\"{$this->select_id}div\">";
        echo '<select name="' . $this->select_name . '" id="' . $this->select_id . '" class="form-control input-sm form-inline" style="width: auto;">';
        echo '<option value="">' . $this->defaultlabel . '</option>';
        if ($this->showNull) {
            echo '<option value="NULL">VIDER (NULL)</option>';
        }

        // La table sujet a deux sous-groupes, elle est traitée à part
        if ($this->tbl_name == 'Sujet') {
            $this->subjectSelectData();
        } else {
            // pour toutes les tables sauf les sujets
            $this->tableSelectData();
        }

        echo '</select>';
        if (!$this->ajax) {
            echo '</div>';
        } else {
            return;
        }


        //
        // Boutons de gestion si l'utilisateur est admin
        //
         if ($this->frm_classname) {
            $divid = $this->select_id . 'Dialog';

            echo " " . CHtml::ajaxButton(
                    "+", Yii::app()->createUrl("admin/addSmallListEntry/type/$this->tbl_name/id/$this->select_id"), array('update' => '#' . $divid), array('onclick' => '$("#' . $divid . '").dialog("open");', 'class' => "btn btn-default btn-sm")
            );

            // Affichage du dialogue
            $this->beginWidget('zii.widgets.jui.CJuiDialog', array(
                'id' => $divid,
                // additional javascript options for the dialog plugin
                'options' => array(
                    'title' => "$this->tbl_name : ajout d'une entrée",
                    'autoOpen' => false,
                    'close' => 'js:function(){
                    jQuery.ajax({
                        "url":"' . Yii::app()->createUrl("admin/refreshselect/type/$this->tbl_name") . '",
                        "cache":false,
                        "success":function(html){jQuery("#' . $this->select_id . 'div").html(html)}
                          })}',
                    'buttons' => array(
                        'Annuler' => 'js:function(){
                                    $(this).dialog("close");
                                    }',
                        'Ajouter' => 'js:function(){
                                    $.post(
                                        $("#' . $this->select_id . 'Form").attr("action"), // the url to submit to
                                        $("#' . $this->select_id . 'Form").serialize(), // the data is serialized
                                        function(){$("#' . $divid . '").dialog("close");} // in the success the dialog is closed
                                    );
                            }',
            ))));
            $this->endWidget('zii.widgets.jui.CJuiDialog');
        }
    }

    private function subjectSelectData() {


        $lists = array(
            'Sciences humaines' => "shs=1",
            'Sciences biomédicales' => "stm=1");

        foreach ($lists as $group => $condition) {
            echo '<optgroup label="' . $group . '">';
            foreach ($this->subjectData($condition) as $s) {
                echo "<option value=\"$s->sujet_id\"";
                if ($this->selected == $s->sujet_id) {
                    echo " selected";
                }
                echo ">$s->nom_fr</option>";
            }
            echo '</optgroup>';
        }
    }
    
    private function subjectData ($condition){
        $selectData = Yii::app()->cache->get($this->tbl_name_low.$condition);
        if ($selectData === false) {
            // Régénère $selectData car il ne se trouve pas dans le cache
            $selectData = $this->model->findAll(array('condition' => $condition, 'order' => 'nom_fr'));
            // Sauvegarde pour une utlisation utlérieure
            Yii::app()->cache->set($this->tbl_name_low, $selectData);
        }
        return $selectData;
    }

    private function tableSelectData() {
        $selectData = Yii::app()->cache->get($this->tbl_name_low);
        if ($selectData === false) {
            // Régénère $selectData car il ne se trouve pas dans le cache
            $selectData = $this->model->findAll(array('order' => $this->column));
            // Sauvegarde pour une utlisation utlérieure
            Yii::app()->cache->set($this->tbl_name_low, $selectData);
        }
        foreach ($selectData as $m) {
            echo '<option value="' . $m->{$this->tbl_id} . '"';
            if ($this->selected == $m->{$this->tbl_id}) {
                echo " selected";
            }
            echo '>' . $this->trim_text($m->{$this->column}, 70) . '</option>';
        }
    }

    /**
     * trims text to a space then adds ellipses if desired
     * @param string $input text to trim
     * @param int $length in characters to trim to
     * @param bool $ellipses if ellipses (...) are to be added
     * @param bool $strip_html if html tags are to be stripped
     * @return string
     */
    private function trim_text($input, $length, $ellipses = true, $strip_html = true) {
        //strip tags, if desired
        if ($strip_html) {
            $input = strip_tags($input);
        }

        //no need to trim, already shorter than trim length
        if (strlen($input) <= $length) {
            return $input;
        }

        //find last space within length
        $last_space = strrpos(substr($input, 0, $length), ' ');
        $trimmed_text = substr($input, 0, $last_space);

        //add ellipses (...)
        if ($ellipses) {
            $trimmed_text .= '...';
        }

        return $trimmed_text;
    }

}
