<?php

class SearchComponent extends CComponent {
    /*
     * Constantes pour la recherche simple 
     */

    const TEXACT = 'texact'; // Recherche selon la phrase exacte entrée par l'utilisateur
    const TBEGIN = 'tbegin'; // Recherche selon les premier mots du titre
    const TWORDS = 'twords'; // Recherche de tous les mots indépendamment
    const JRNALL = 'jrnall'; // Recherche dans tous les champs publiques de la table Journal

    /**
     * Si true, les périodiques du dépot légal sont inculs dans la recherche.
     * @var bool false par défaut.
     */

    public $depotlegal = false;

    const depotlegal_idlocalisation = '24, 25, 26, 27';
    const BiUM_Corecollection = 6;

    /**
     * Requête de la recherche avancée
     * @var array 
     */
    protected $adv_query_tab;
    protected $adv_sql_command;
    protected $adv_count;
    protected $adv_dp;

    /**
     * Requête de la recherche simple
     * @var type 
     */
    protected $simple_query_str;
    /* @var $simple_sql_cmd CDbCommand */
    protected $simple_sql_cmd;
    protected $simple_sql_query_count;
    protected $simple_dp;

    /**
     * Requête de la recherche admin
     * @var type 
     */
    protected $admin_query_tab;
    protected $admin_criteria;
    protected $admin_affichage;
    protected $admin_count;
    protected $admin_dp;

    /**
     * $query après les traitements de base
     * @var string 
     */
    protected $q;

    /**
     * Option pour la recherche simple. Définie en constante de classe.
     * @var string constante. Par défaut TWORD.
     */
    public $search_type = self::TWORDS;

    /**
     * Résumé de la recherce effectuée.
     * @var string 
     */
    private $q_summary = "";

    /**
     * Si fixé, ajoute ou supprime les abonnement dont le titre est exclu.
     * Par défaut, null : ce critère n'est pas pris en compte dans la recherche.
     * @var int 
     */
    public $titreexclu = null;

    /**
     * Type de support du périodique (0 = tous)
     * @var int 0 par défaut
     */
    public $support = 0;

    /**
     * Nombre de résultats affichés par page.
     * @var int 
     */
    public $pagesize = 30;
    public $maxresults = -1;

///////////////////////////////////////////////////////////////////////////
// Recherche avancée
///////////////////////////////////////////////////////////////////////////
// Méthodes publiques
//===================

    /**
     * Assigne la requête de recherche avancée
     * @param array $query_tab 
     * [C1][search_type] :  partout, titre, editeur, issn
     * [C1][text]        : texte libre de la recherche
     * [C1][op]          : Opérateur de liaison avec le criètre suivant
     * ...
     * [C3] ! ne contient pas de champ 'op'
     * [support]      : 0 (Tous), 1 (électronique), 2 (papier)
     * [accessunil]   : 0/1
     * [openaccess]   : 0/1
     * [sujet]        : Sujet.sujet_id, defaut all
     * [plateforme]   : Plateforme.plateform_id, defaut all
     * [licence]      : Licence.licence_id, defaut all
     * [statutabo]    : Statutabo.statutabo_id, defaut all
     * [localisation] : Localisation.localisation_id, defaut all
     */
    public function setAdv_query_tab($query_tab) {

        $this->adv_query_tab = $query_tab;
//$this->adv_criteria = $this->advancedSearch();
        //$this->advancedSearch(); // => $this->adv_sql_command
    }

    public function getAdv_query_tab() {
        if (isset($this->adv_query_tab)) {
            return $this->adv_query_tab;
        } else {
            return null;
        }
    }

    public function getAdv_dp() {
        if (isset($this->adv_query_tab)) {
            $this->advancedSearch();
            $rawData = $this->adv_sql_command->queryAll();
            $this->adv_count = count($rawData);
            $this->adv_dp = new CArrayDataProvider($rawData, array(
                'keyField' => 'perunilid',
                'pagination' => array(
                    'pageSize' => $this->pagesize,
                ),
            ));
        } else {
            throw new CException("Il n'existe aucune requête en mémoire pour afficher les résultats de la recherche avancée.");
        }

        return $this->adv_dp;
    }

    public function getAdv_adp() {

        if (isset($this->adv_query_tab)) {
            $this->advancedSearch();
            $rawData = $this->adv_sql_command->queryAll();
            $this->adv_count = count($rawData);
            $idlist = array_map('current', $rawData);

            $criteria = new CDbCriteria();
            $criteria->addInCondition('perunilid', $idlist);


            $adp = new CActiveDataProvider('Abonnement', array(
                'criteria' => $criteria,
                'pagination' => array(
                    'pageSize' => $this->pagesize,
                ),
            ));
        } else {
            throw new CException("Il n'existe aucune requête en mémoire pour afficher les résultats de la recherche avancée.");
        }
        return $adp;
    }

    public function getAdv_count() {
        return $this->adv_count;
    }

    /* public function setAdv_dp($dp) {
      $this->adv_dp = $dp;
      } */

// Méthode privées
//================

    /**
     * Crée le critère de recherche pour la recherche avancée
     * @return CDbCriteria
     * @throws CException 
     */
    private function advancedSearch() {
        $this->q_summary = "";

// Vérification de l'existance et de la conformité de la requête.
        if (!$this->adv_query_tab && !is_array($this->adv_query_tab)) {
            throw new CException("Recherche avancée impossible : requête n'est enregistrée.");
        }

        $c = Yii::app()->db->createCommand();

        $c->selectDistinct('j.perunilid');
        $c->from('journal j');


// Jointure des abonnements
// Si public, seulement les journaux qui ont un abonnement
        if (Yii::app()->user->isGuest) {
            $c->join('abonnement a', 'j.perunilid = a.perunilid AND a.titreexclu = 0 ');
        }
// Si admin, tous les journaux, même sans abonnement
        else {
            $c->leftJoin('abonnement a', 'j.perunilid = a.perunilid');
        }



// 1. Jointure en fonction des limitations demandées

        if (isset($this->adv_query_tab['plateforme']) && $this->adv_query_tab['plateforme'] != '') {
            $c->join(
                    'plateforme pl', "a.plateforme = pl.plateforme_id AND pl.plateforme_id = :idpl", array(':idpl' => $this->adv_query_tab['plateforme'])
            );
            $this->query_summary("Plateforme = « " . Plateforme::model()->findByPk($this->adv_query_tab['plateforme'])->plateforme . " »");
        }

        if (isset($this->adv_query_tab['licence']) && $this->adv_query_tab['licence'] != '') {
            $c->join(
                    'licence li', "a.licence = li.licence_id AND li.licence_id = :idli", array(':idli' => $this->adv_query_tab['licence'])
            );
            $this->query_summary("Licence = « " . Licence::model()->findByPk($this->adv_query_tab['licence'])->licence . " »");
        }

        if (isset($this->adv_query_tab['statutabo']) && $this->adv_query_tab['statutabo'] != '') {
            $c->join(
                    'statutabo st', "a.statutabo = st.statutabo_id AND st.statutabo_id = :idst", array(':idst' => $this->adv_query_tab['statutabo'])
            );
            $this->query_summary("Abonnement = « " . Statutabo::model()->findByPk($this->adv_query_tab['statutabo'])->statutabo . " »");
        }

        if (isset($this->adv_query_tab['localisation']) && $this->adv_query_tab['localisation'] != '') {
            $c->join(
                    'localisation lo', "a.localisation = lo.localisation_id AND lo.localisation_id = :idlo", array(':idlo' => $this->adv_query_tab['localisation'])
            );
            $this->query_summary("Localisation = « " . Localisation::model()->findByPk($this->adv_query_tab['localisation'])->localisation . " »");
        }


// Jointure avec la table sujet
        if (isset($this->adv_query_tab['sujet']) && $this->adv_query_tab['sujet'] != '') {
            $c->join(
                    "journal_sujet js", "js.perunilid = j.perunilid");
            $c->join(
                    "sujet s", "s.sujet_id = js.sujet_id AND s.sujet_id = :sid", array(":sid" => $this->adv_query_tab['sujet'])
            );
            $this->query_summary("Sujet = « " . Sujet::model()->findByPk($this->adv_query_tab['sujet'])->nom_fr . " »");
        }

// Pour les critère d'accès, unil-chuv et openaccès, on ne traite que si c'est décoché
        if (!isset($this->adv_query_tab['accessunil']) || !$this->adv_query_tab['accessunil']) {
            $c->andWhere("a.acces_elec_unil !=1 && a.acces_elec_chuv !=1");
            $this->query_summary("sans les abonnements UNIL et CHUV.");
        }
        if (!isset($this->adv_query_tab['openaccess']) || !$this->adv_query_tab['openaccess']) {
            $c->andWhere("a.acces_elec_gratuit !=1 && j.openaccess !=1");
            $this->query_summary("sans les jouraux Openaccess.");
        }



        $Cwhere = "";
        $editorAlreadyJointed = false;
        $plateformeAlreadyJointed = false;
        foreach (array('C1', 'C2', 'C3') as $CN) {
            if (!isset($this->adv_query_tab[$CN]))
                continue;
// nettoyage du champ
            $this->simple_query_str = $this->adv_query_tab[$CN]['text'];
            $this->q = $this->clean_search($this->simple_query_str);
// si le champ ne contient rien , on abandonne ici.
            if ($this->q == "")
                continue;
            else { // Traitement du champ CN
                $like = ' LIKE ';
                switch ($this->adv_query_tab[$CN]['op']) {
                    case 'OR':
                        $Cwhere .= " OR ";
                        break;
                    case 'NOT': // AND ... NOT LIKE...
                        $like = " NOT LIKE ";
                    case 'AND':
                        $Cwhere .= " AND ";
                        break;
                    default:
                        throw new CException("L'opperateur {$this->adv_query_tab[$CN]['op']} n'existe pas dans les option proposées");
                        break;
                }

                switch ($this->adv_query_tab[$CN]['search_type']) {

                    case 'issn':
                        $issn = trim($this->simple_query_str);
// Ajout du - comme 5ème caratère si nécessaire
                        if (strpos($issn, '-') === FALSE) {
                            $issn = substr_replace($issn, '-', 4, 0);
                        }
                        $Cwhere .= " (j.issn $like '%$issn%' OR j.issnl $like '%$issn%') ";
                        $this->query_summary("issn = $this->simple_query_str");
                        break;

                    case 'titre':
                        $Twhere = "";
                        $tokens = array();

                        foreach (explode(" ", $this->q) as $word) {
                            if ($word != "" || $word != "") {
                                $tokens[] = Yii::app()->db->quoteValue("%$word%");
                            }
                        }

                        $cols = array('j.titre', 'j.titre_abrege', 'j.titre_variante', 'j.soustitre', 'j.faitsuitea', 'j.devient');
// Boucle sur toutes les colonnes
                        foreach ($cols as $col) {
                            $Twhere .= " (";
// Boucle sur touts les mots de la recherche
                            foreach ($tokens as $word) {
                                $Twhere .= "$col $like $word AND ";
                            }
// Suppression d'un OR surnuméraire
                            $Twhere = trim($Twhere, "AND ");
                            $Twhere .= " ) OR ";
                        }

// Suppression d'un AND surnuméraire
                        $Twhere = trim($Twhere, " OR ");
// Ajout de la requête des titres à la requête générale

                        $Cwhere .= " ( $Twhere ) ";
                        $this->query_summary("titre : '$this->q'");
                        break;

                    case 'editeur':
                        if (!$editorAlreadyJointed) {
                            $c->leftjoin(
                                    'editeur ed', "a.editeur = ed.editeur_id "//AND ed.editeur LIKE :editeur", array(':editeur' => "%$this->q%")
                            );
                            $editorAlreadyJointed = true;
                        }
                        $this->query_summary("éditeur ou plateforme contenant l'expression : « " . $this->q . " »");

// Recherche dans la plateforme
// Si la plateforme n'as pas encore été jointe, on l'associe.
                        if (!$plateformeAlreadyJointed) {
                            if (!(isset($this->adv_query_tab['plateforme']) && $this->adv_query_tab['plateforme'] != '')) {
                                $c->leftjoin(
                                        'plateforme pl', "a.plateforme = pl.plateforme_id"
                                );
                                $plateformeAlreadyJointed = true;
                            }
                        }
                        $quotedq = Yii::app()->db->quoteValue("%$this->q%");
                        $Cwhere .= " (ed.editeur LIKE $quotedq OR pl.plateforme LIKE $quotedq) ";

                        break;

                    default:
                        throw new CException("Le critère {$this->adv_query_tab[$CN]['search_type']} n'existe pas pour la recherche avancée");
                        break;
                }
            }
        }

// Si une requête à été générée pour les CN, il faut enlever les conjonction surnuméraires
        if ($Cwhere != "") {
            $Cwhere = trim($Cwhere, "OR ");
            $Cwhere = trim($Cwhere, "AND ");
// Ajour de Cwhere à la requête générale
            $c->andWhere($Cwhere);
        }

// Ajout des abonnements du dépot légal
        if ($this->depotlegal) {
            $this->query_summary("avec les périodiques du dépot légal BCU");
        } else {
            $c->andWhere("(a.localisation NOT IN (" . self::depotlegal_idlocalisation . ") OR a.localisation IS NULL)");
        }


        $c->order("j.titre");

        $sql = $c->text;

        $this->adv_sql_command = $c;

//return $criteria;
// Gérération d'une requête count
//$c->select = "SELECT DISTINCT COUNT(*) ";
    }

///////////////////////////////////////////////////////////////////////////
// Recherche simple
///////////////////////////////////////////////////////////////////////////
// Méthodes publiques
//===================


    public function setSimple_query_str($query_str) {
        $this->simple_query_str = $query_str;
    }

    public function getSimple_query_str() {
        if (isset($this->simple_query_str)) {
            return $this->simple_query_str;
        } else {
            return null;
        }
    }

    private function generateSimpleSqlCmd() {
        $query_str = $this->getSimple_query_str();
        if (empty($query_str)) {
            throw new CException("Il n'existe aucune requête en mémoire pour afficher les résultats de la recherche simple.");
        }
        $simpleSearch = new SimpleSearchComponent;
        $this->simple_sql_cmd = $simpleSearch->getSimpleCdbCommand($query_str, $this->search_type);
    }

    public function getSimple_dp() {
        $this->generateSimpleSqlCmd();


        $rawData = $this->simple_sql_cmd->queryAll();
        $this->simple_sql_query_count = count($rawData);
        $this->simple_dp = new CArrayDataProvider($rawData, array(
            'keyField' => 'perunilid',
            //'sort' => array(
            //    'attributes' => array(
            //        'id', 'username', 'email',
            //    ),
            //),
            'pagination' => array(
                'pageSize' => $this->pagesize,
            ),
        ));

        return $this->simple_dp;
    }

    /**
     *  Crée un ActiveDataProvider a partir de la liste des perunilid issus de
     * la requête. Utilisé pour l'affichage par abonnements.
     * @return \CActiveDataProvider
     */
    public function getSimple_adp() {
        $this->generateSimpleSqlCmd();

        $rawData = $this->simple_sql_cmd->queryAll();
        $this->simple_sql_query_count = count($rawData);

        $idlist = array_map('current', $rawData);

        $criteria = new CDbCriteria();
        $criteria->addInCondition('perunilid', $idlist);


        $adp = new CActiveDataProvider('Abonnement', array(
            'criteria' => $criteria,
            'pagination' => array(
                'pageSize' => $this->pagesize,
            ),
        ));

        return $adp;
    }

    public function getSimple_sql_query_count() {
        return $this->simple_sql_query_count;
    }

    public function getSimple_sql_query() {
        return $this->simple_sql_cmd;
    }

    /**
     * Recherche de chaque mot indépendamment dans tous les champs public 
     * de la table journal.
     * @param CDbCriteria $criteria Requête en cours de construction, passage par référence
     * @param boolean $not_like false par défaut. Si true, la requête est construite
     *                          avec des "NOT LIKE". 
     */
    private function journalSearch($criteria, $not_like = false) {
        $like = $not_like ? "NOT LIKE" : "LIKE";
        foreach (explode(" ", $this->q) as $word) {
            if ($word != "" || $word != "") {
                $word = Yii::app()->db->quoteValue("%$word%");
                $query = "t.perunilid $like $word OR titre $like $word " .
                        "OR titre_abrege $like $word OR titre_variante $like $word " .
                        "OR soustitre $like $word OR issn $like $word " .
                        "OR issnl $like $word OR nlmid $like $word " .
                        "OR reroid $like $word OR doi $like $word " .
                        "OR coden $like $word OR urn $like $word " .
                        "OR faitsuitea $like $word OR devient $like $word " .
                        "OR url_rss $like $word ";
                $criteria->addCondition($query, 'AND');
            }
        }
        $this->query_summary("'$this->q' dans tous les champs de la table journal.");
    }

    /* private function aboSearch($criteria, $not_like = false) {
      $like = $not_like ? "NOT LIKE" : "LIKE";
      foreach (explode(" ", $this->q) as $word) {
      if ($word != "" || $word != "") {
      $query = "abonnements.package $like '%$word%' OR abonnements.url_site $like '%$word%' " .
      "OR abonnements.etatcoll $like '%$word%' OR abonnements.cote $like '%$word%' " .
      "OR abonnements.editeur_code $like '%$word%' OR abonnements.editeur_sujet $like '%$word%' " .
      "OR abonnements.commentaire_pub $like '%$word%' ";
      $criteria->addCondition($query, 'AND');
      }
      }
      } */

    public function setSearch_type($search_type) {
        if (defined("self::" . strtoupper($search_type)))
            $this->search_type = $search_type;
        else
            throw new CException("Type de recherche invalide : $search_type.");
    }

    public function getSearch_type() {
        return $this->search_type;
    }

    /**
     * Function pour nettoyer les critères de recherche (mots vides, ponctuation...)
     * @param string $original
     * @return string 
     */
    protected function clean_search($original) {
        $var = trim($original);

        // Suppression de guillemet et apostrophes
        $var = str_replace('"', " ", $var);
        $var = str_replace("'", " ", $var);

        $var = " " . $var . " ";
        $var = str_ireplace(",", "", $var);
        $var = str_ireplace(". ", " ", $var);
        $var = str_ireplace(": ", " ", $var);
        $var = str_ireplace(":", " ", $var);
//$var = str_ireplace("-", " ", $var); // Pour les issn, il ne faut pas retirer le -
        $var = str_ireplace(";", "", $var);
        $var = str_ireplace(" (the) ", " ", $var);
        $var = str_ireplace(" the ", " ", $var);
        $var = str_ireplace(" [the] ", " ", $var);
        $var = str_ireplace(" of ", " ", $var);
        $var = str_ireplace(" de ", " ", $var);
        $var = str_ireplace(" du ", " ", $var);
        $var = str_ireplace(" le ", " ", $var);
        $var = str_ireplace(" les ", " ", $var);
        $var = str_ireplace(" des ", " ", $var);
        $var = str_ireplace(" l'", " ", $var);
        $var = str_ireplace(" la ", " ", $var);
        $var = str_ireplace(" los ", " ", $var);
        $var = str_ireplace(" el ", " ", $var);
        $var = str_ireplace(" and ", " ", $var);
        $var = str_ireplace(" (and) ", " ", $var);
        $var = str_ireplace(" [and] ", " ", $var);
        $var = str_ireplace(" et ", " ", $var);
        $var = str_ireplace(" (et) ", " ", $var);
        $var = str_ireplace(" [et] ", " ", $var);
        $var = str_ireplace(" y ", " ", $var);
        $var = str_ireplace(" und ", " ", $var);
        $var = str_ireplace(" der ", " ", $var);
        $var = str_ireplace(" die ", " ", $var);
        $var = str_ireplace(" das ", " ", $var);
        $var = str_ireplace(" fur ", " ", $var);
        $var = str_ireplace(" für ", " ", $var);
        $var = str_ireplace(" & ", " ", $var);
        $var = str_ireplace(" (&) ", " ", $var);
        $var = str_ireplace(" [&] ", " ", $var);
        $var = str_ireplace(" &amp ", " ", $var);

// Si l'application de clean_search à été destructive, on revient
// à la donnée initiale.
        if (($var == "") || ($var == " ")) {
            $var = $original;
        }
        $var = trim($var);
        str_ireplace("*", "%", $var);
        return $var;
    }

///////////////////////////////////////////////////////////////////////////
// Recherche admin
///////////////////////////////////////////////////////////////////////////
// Méthodes publiques
//===================

    public function setAdmin_query_tab($query_tab) {
        $this->admin_query_tab = $query_tab;
// $this->refreshAdminCriteria();
    }

    public function setAdmin_affichage($affichage = 'abonnement') {

        if ($affichage == 'journal') {
            $this->admin_affichage = 'journal';
        } else {
            $this->admin_affichage = 'abonnement';
        }
// $this->refreshAdminCriteria();
    }

    public function getAdmin_affichage() {
        if (isset($this->admin_affichage) && in_array($this->admin_affichage, array("journal", "abonnement"))) {
            return $this->admin_affichage;
        } else {
            return 'journal';
        }
    }

//    private function refreshAdminCriteria() {
//        $this->q_summary = "";
//        if (isset($this->admin_affichage) && $this->admin_affichage == 'journal') {
//            $this->admin_criteria = $this->adminSearch();
//        } else {
//            $this->admin_criteria = $this->aboadminSearch();
//        }
//    }

    public function getAdmin_criteria() {
        if ($this->getAdmin_affichage() == 'journal') {
            $this->admin_criteria = $this->adminSearch();
        } else {
            $this->admin_criteria = $this->aboadminSearch();
        }
        return $this->admin_criteria;
    }

    public function getAdmin_query_tab() {
        if (isset($this->admin_query_tab)) {
            return $this->admin_query_tab;
        } else {
            return null;
        }
    }

    public function getAdmin_dp() {
        $affichage = ucfirst($this->getAdmin_affichage());

        $this->q_summary = "";
        $this->getAdmin_criteria();


        $this->admin_dp = new CActiveDataProvider(
                $affichage::model(), array('criteria' => $this->admin_criteria,
            'pagination' => array('pageSize' => $this->pagesize))
        );

        $this->admin_count = $this->admin_dp->totalItemCount;
        return $this->admin_dp;
    }

    public function getAdmin_count() {
        return $this->admin_count;
    }

    /**
     * Recherche selon un ou plusieurs critère du tableau $querytab : 
     *  [perunilidcrit1]	string	"equal"	
     *   [perunilid1]	string	""	
     *   [perunilidcrit2]	string	"equal"	
     *   [perunilid2]	string	""		
     *   [titre]	string	""	
     *   [soustitre]	string	""	
     *   [titreabrege]	string	""	
     *   [variantetitre]	string	""	
     *   [faitsuitea]	string	""	
     *   [devient]	string	""	
     *   [editeur]	string	""	
     *   [codeediteur]	string	""	
     *   [issnl]	string	""	
     *   [issn]	string	""	
     *   [reroid]	string	""	
     *   [nlmid]	string	""	
     *   [coden]	string	""	
     *   [doi]	string	""	
     *   [urn]	string	""	
     *   [url]	string	""	
     *   [rss]	string	""	
     *   [user]	string	""	
     *   [pwd]	string	""	
     *   [licence]	foreign key	
     *   [statutabo]	foreign key	
     *   [plateforme]	foreign key	
     *   [gestion]	foreign key
     *   [histabo]	foreign key
     *   [support]	foreign key
     *   [format]	foreign key
     *   [package]	string	""	
     *   [no_abo]	string	""	
     *   [etatcoll]	string	""	
     * [embargocrit]	string	"equal"
     * [embargo]	string	""
     * [etatcolldeba]	string	""
     * [etatcolldebv]	string	""
     * [etatcolldebf]	string	""
     * [etatcollfina]	string	""
     * [etatcollfinv]	string	""
     * [etatcollfinf]	string	""
     * [localisation]	string	""
     * [cote]	string	""	
     * [commentairepro]	string	""	
     * [commentairepub]	string	""	
     * [sujet]	string	""
     * [sujetsfm]	string	""	
     * [fmid]	string	""	
     * [historique]	string	""	
     * @param array $qt 
     * @return CActiveDataProvider correspondant à la requête. NULL si aucun
     *                             critère n'a été fourni.
     */
    private function adminSearch() {
        $qt = $this->admin_query_tab;
        $limite = 100;
        $ct = array('equal' => '=', 'before' => '<', 'after' => '>');

        $criteria = new CDbCriteria();
        $this->joinAbo($criteria);

// Jointure de la table sujet si nécessaire
        if (trim($qt['sujet'])) {
            $criteria->join .= 'LEFT JOIN `journal_sujet` `js` ON (`js`.`perunilid`=`t`.`perunilid`)';
            $criteria->join .= 'LEFT JOIN `sujet` `s` ON (`s`.`sujet_id`=`js`.`sujet_id`)';
            $criteria->addCondition("s.sujet_id ='{$qt['sujet']}'");
            $this->query_summary("sujet = " . Sujet::model()->findByPk($qt['sujet'])->nom_fr);
        }

// Jointure de la table editeur si nécessaire
        if (trim($qt['editeur_txt'])) {
            $criteria->join .= 'LEFT JOIN editeur ed ON (ed.editeur_id=abonnements.editeur)';
        }

//Recherche par perunilid
        if (trim($qt['perunilid1'])) {
            $criteria->addCondition("t.perunilid " . $ct[$qt['perunilidcrit1']] . " '" . $qt['perunilid1'] . "'");
            $this->query_summary("perunilid " . $ct[$qt['perunilidcrit1']] . " " . $qt['perunilid1']);
// S'il y a un deuxième perunilid
            if (trim($qt['perunilid2'])) {
                $criteria->addCondition("t.perunilid " . $ct[$qt['perunilidcrit2']] . " '" . $qt['perunilid2'] . "'");
                $this->query_summary("perunilid " . $ct[$qt['perunilidcrit2']] . " " . $qt['perunilid2']);
            }
        }

        if (isset($qt['corecollection'])) {
            if ($qt['corecollection'] == 'VRAI') { // Joindre la corecollection BiUM
                $criteria->join .= 'INNER JOIN corecollection AS cc ON t.perunilid = cc.perunilid AND cc.biblio_id = ' . self::BiUM_Corecollection;
                $this->query_summary("avec la core collection BiUM");
            } elseif ($qt['corecollection'] == 'FAUX') { // Exclure la corecollection BiUM
                $criteria->addCondition("t.perunilid NOT IN (SELECT c.perunilid FROM corecollection AS c WHERE c.biblio_id = " . self::BiUM_Corecollection . ")");
                $this->query_summary("sans la core collection BiUM");
//$criteria->join .= 'LEFT JOIN corecollection AS cc ON t.perunilid = cc.perunilid AND cc.biblio_id != ' . self::BiUM_Corecollection;
            }
        }
// Jointure pour corecollection
// $q .="LEFT JOIN corecollection AS cc ON j.perunilid = cc.perunilid ";
// $q .="LEFT JOIN biblio AS bib ON bib.biblio_id = cc.biblio_id ";
// Modifications : Si un champ concernant le modifications est rempli
// 1. Création d'une requête pour la table modification
// 2. Liste de Perunilid ou d'abonnement_id comme resultat de la requête
//    avec une maximum selon $limite
// 3. la liste d'id est passée à une clause IN dans la requête principale.
        if (trim($qt['signaturecreation']) || trim($qt['signaturemodification']) || trim($qt['datecreation1']) || trim($qt['datemodif1'])) {


            $where_string = "";
            $where_array = array();

// Préparation de la requête pour des recherche sur la création
            if (trim($qt['datecreation1'])) {
                $phpdate = strtotime(trim($qt['datecreation1']));
                $mysqldate = date('Y-m-d H:i:s', $phpdate);
                $where_string = "stamp " . $ct[$qt['datecreationcrit1']] . " :stamp";
                $where_array[":stamp"] = $mysqldate;
                $this->query_summary("date de création " . $ct[$qt['datecreationcrit1']] . " " . $qt['datecreation1']);

                if (trim($qt['datecreation2'])) {
                    $phpdate = strtotime(trim($qt['datecreation2']));
                    $mysqldate = date('Y-m-d H:i:s', $phpdate);
                    $where_string .= " AND stamp " . $ct[$qt['datecreationcrit2']] . " :stampi";
                    $where_array[":stampi"] = $mysqldate;
                    $this->query_summary(" et " . $ct[$qt['datecreationcrit2']] . " " . $qt['datecreation2']);
                } // datecreation2
            } // datecreation1
// Recherche d'après le créateur
            if (trim($qt['signaturecreation'])) {
                $s = trim($qt['signaturecreation']);
                if ($where_string != "")
                    $where_string .= " AND ";
                $where_string .= 'user_id = :sc';
                $where_array[':sc'] = $s;
                $this->query_summary("Signature de création = " . Utilisateur::model()->findByPk($s)->pseudo);
            }

// ---
// Préparation de la requête pour des recherche sur la modification
            if (trim($qt['datemodif1'])) {
                $phpdate = strtotime(trim($qt['datemodif1']));
                $mysqldate = date('Y-m-d H:i:s', $phpdate);
                if ($where_string != "")
                    $where_string .= " AND ";
                $where_string .= "stamp " . $ct[$qt['datemodifcrit1']] . " :stampii";
                $where_array[":stampii"] = $mysqldate;
                $this->query_summary("date de modification " . $ct[$qt['datemodifcrit1']] . " " . $qt['datemodif1']);


                if (trim($qt['datemodif2'])) {
                    $phpdate = strtotime(trim($qt['datemodif2']));
                    $mysqldate = date('Y-m-d H:i:s', $phpdate);
                    $where_string .= " AND stamp " . $ct[$qt['datemodifcrit2']] . " :stampiii";
                    $where_array[":stampiii"] = $mysqldate;
                    $this->query_summary(" et " . $ct[$qt['datemodifcrit2']] . " " . $qt['datemodif2']);
                } // datemodif2
            } // datemodif1
// Recherche d'après le modificateur
            if (trim($qt['signaturemodification'])) {
                $s = trim($qt['signaturemodification']);
                if ($where_string != "")
                    $where_string .= " AND ";
                $where_string .= 'user_id = :sm';
                $where_array[':sm'] = $s;
                $this->query_summary("Signature de modification = " . Utilisateur::model()->findByPk($s)->pseudo);
            }

            $ids = array();
            foreach (array('journal', 'abonnement') as $model) {
                $where_string .= " AND action = :act AND model = :model";
                $where_array[':act'] = 'Création';
                $where_array[':model'] = $model;
                $cmd = Yii::app()->db->createCommand()
                        ->selectDistinct('m.model_id')
                        ->from('modifications m')
                        ->where($where_string)
//->where('user_id = :sc AND action = :act AND model = :mod', array(':sc' => $s, ':act' => 'Création', ':mod' => 'journal'))
                        ->limit($limite)
                        ->order("stamp DESC");

                $perunilids = $cmd->queryAll(true, $where_array);
                $ids[$model] = join("','", array_map('current', $perunilids));
            }

// FIXME : ne pas ajouter la condition si $ids[$model] est vide.
            $criteria->addCondition("t.perunilid IN ('{$ids['journal']}') OR abonnements.abonnement_id IN ('{$ids['abonnement']}')");
        } // Modifications
// Recherche tous les champs
        if (trim($qt['all'])) {
            $this->q = $qt['all'];
            $this->journalSearch($criteria);
        }

// Recherche de tous les champs au format texte : "LIKE %$term%"
        $textfield = @array(
            't.titre' => explode(" ", $qt['titre']),
            't.soustitre' => explode(" ", $qt['soustitre']),
            't.titre_abrege' => explode(" ", $qt['titreabrege']),
            't.titre_variante' => explode(" ", $qt['variantetitre']),
            't.faitsuitea' => explode(" ", $qt['faitsuitea']),
            't.devient' => explode(" ", $qt['devient']),
            'ed.editeur' => explode(" ", $qt['editeur_txt']),
            't.issnl' => $qt['issnl'],
            't.issn' => $qt['issn'],
            't.reroid' => $qt['reroid'],
            't.nlmid' => $qt['nlmid'],
            't.coden' => $qt['coden'],
            't.doi' => $qt['doi'],
            't.urn' => $qt['urn'],
            't.url_rss' => $qt['rss'],
            'abonnements.url_site' => $qt['url'],
            'abonnements.editeur_code' => $qt['codeediteur'],
            'abonnements.acces_user' => $qt['user'],
            'abonnements.acces_pwd' => $qt['pwd'],
            'abonnements.package' => $qt['package'],
            'abonnements.no_abo' => $qt['no_abo'],
            'abonnements.etatcoll' => $qt['etatcoll'],
            'abonnements.cote' => $qt['cote'],
            'abonnements.commentaire_pro' => $qt['commentairepro'],
            'abonnements.commentaire_pub' => $qt['commentairepub'],
            't.commentaire_pub' => $qt['commentairepub'],
            't.DEPRECATED_sujetsfm' => $qt['sujetsfm'],
            't.DEPRECATED_fmid' => $qt['fmid'],
            //'t.DEPRECATED_historique' => $sm_name,
            't.DEPRECATED_historique' => $qt['historique'],
        );
        foreach ($textfield as $column => $value) {
// Pour champs dont on fait un recherche terme à terme
            if (is_array($value) && count($value) > 0) {
                $query = new CDbCriteria();
                foreach ($value as $term) {
                    $term = trim($term);
                    if ($term) {
                        $query->addSearchCondition($column, $term);
                        $this->query_summary("$column LIKE %$term%");
                    }
                }
                $criteria->mergeWith($query);
            }
// Pour les champs dont la recherche ne porte que sur un seul terme
            else {
                $value = trim($value);
                if ($value) {
                    $criteria->addSearchCondition($column, $value);
                    $this->query_summary("$column LIKE %$value%");
                }
            }
        }

// Recherche exacte : "= $term"
        $exact_fields = @array(
            't.openaccess' => $qt['openaccess'],
            't.publiunil' => $qt['publiunil'],
            't.parution_terminee' => $qt['parution_terminee'],
            'abonnements.licence' => $qt['licence'],
            'abonnements.statutabo' => $qt['statutabo'],
            'abonnements.plateforme' => $qt['plateforme'],
            'abonnements.gestion' => $qt['gestion'],
            'abonnements.histabo' => $qt['histabo'],
            'abonnements.support' => $qt['support'],
            'abonnements.format' => $qt['format'],
            'abonnements.editeur' => $qt['editeur'],
            'abonnements.titreexclu' => $qt['titreexclu'],
            'abonnements.localisation' => $qt['localisation'],
            'abonnements.etatcoll_deba' => $qt['etatcolldeba'],
            'abonnements.etatcoll_debv' => $qt['etatcolldebv'],
            'abonnements.etatcoll_debf' => $qt['etatcolldebf'],
            'abonnements.etatcoll_fina' => $qt['etatcollfina'],
            'abonnements.etatcoll_finv' => $qt['etatcollfinv'],
            'abonnements.etatcoll_finf' => $qt['etatcollfinf'],
            'abonnements.acces_elec_gratuit' => $qt['acces_elec_gratuit'],
            'abonnements.acces_elec_chuv' => $qt['acces_elec_chuv'],
            'abonnements.acces_elec_unil' => $qt['acces_elec_unil'],
        );
        foreach ($exact_fields as $column => $value) {
//$value = trim($value);
            if (strlen(trim($value)) > 0) {
                $criteria->addCondition("$column = '$value'");
                $this->query_summary("$column = $value");
            }
        }

// Traitement du cas de l'embargo
        if (trim($qt['embargo'])) {
            $criteria->addCondition("abonnements.embargo_mois " . $ct[$qt['embargocrit']] . " '" . $qt['embargo'] . "'");
            $this->query_summary("abonnements.embargo_mois " . $ct[$qt['embargocrit']] . " " . $qt['embargo']);
        }
        return $criteria;
        /*
          // S'il n'y auncun critère, on ne revoie rien.
          if (!$criteria->condition) {
          return NULL;
          } else {
          return new CActiveDataProvider(Journal::model(), array('criteria' => $criteria, 'pagination' => array(
          'pageSize' => $this->pagesize)));
          }
         * 
         */
    }

    private function aboadminSearch() {
        $qt = $this->admin_query_tab;
        $limite = 100;
        $ct = array('equal' => '=', 'before' => '<', 'after' => '>');

        $criteria = new CDbCriteria();
        $criteria->join .= 'LEFT JOIN `journal` `j` ON `j`.`perunilid`=`t`.`perunilid` ';

// Jointure de la table sujet si nécessaire
        if (trim($qt['sujet'])) {
            $criteria->join .= 'LEFT JOIN `journal_sujet` `js` ON (`js`.`perunilid`=`t`.`perunilid`)';
            $criteria->join .= 'LEFT JOIN `sujet` `s` ON (`s`.`sujet_id`=`js`.`sujet_id`)';
            $criteria->addCondition("s.sujet_id ='{$qt['sujet']}'");
            $this->query_summary("sujet = " . Sujet::model()->findByPk($qt['sujet'])->nom_fr);
        }

// Jointure de la table editeur si nécessaire
        if (trim($qt['editeur_txt'])) {
            $criteria->join .= 'LEFT JOIN editeur ed ON (ed.editeur_id=t.editeur)';
        }

//Recherche par perunilid
        if (trim($qt['perunilid1'])) {
            $criteria->addCondition("t.perunilid " . $ct[$qt['perunilidcrit1']] . " '" . $qt['perunilid1'] . "'");
            $this->query_summary("perunilid " . $ct[$qt['perunilidcrit1']] . " " . $qt['perunilid1']);
// S'il y a un deuxième perunilid
            if (trim($qt['perunilid2'])) {
                $criteria->addCondition("t.perunilid " . $ct[$qt['perunilidcrit2']] . " '" . $qt['perunilid2'] . "'");
                $this->query_summary("perunilid " . $ct[$qt['perunilidcrit2']] . " " . $qt['perunilid2']);
            }
        }

        if (isset($qt['corecollection'])) {
            if ($qt['corecollection'] == 'VRAI') { // Joindre la corecollection BiUM
                $criteria->join .= 'INNER JOIN corecollection AS cc ON j.perunilid = cc.perunilid AND cc.biblio_id = ' . self::BiUM_Corecollection;
                $this->query_summary("avec la core collection BiUM");
            } elseif ($qt['corecollection'] == 'FAUX') { // Exclure la corecollection BiUM
                $criteria->addCondition("j.perunilid NOT IN (SELECT c.perunilid FROM corecollection AS c WHERE c.biblio_id = " . self::BiUM_Corecollection . ")");
                $this->query_summary("sans la core collection BiUM");
            }
        }

// Modifications : Si un champ concernant le modifications est rempli
// 1. Création d'une requête pour la table modification
// 2. Liste de Perunilid ou d'abonnement_id comme resultat de la requête
//    avec une maximum selon $limite
// 3. la liste d'id est passée à une clause IN dans la requête principale.
        if (trim($qt['signaturecreation']) || trim($qt['signaturemodification']) || trim($qt['datecreation1']) || trim($qt['datemodif1'])) {


            $where_string = "";
            $where_array = array();

// Préparation de la requête pour des recherche sur la création
            if (trim($qt['datecreation1'])) {
                $phpdate = strtotime(trim($qt['datecreation1']));
                $mysqldate = date('Y-m-d H:i:s', $phpdate);
                $where_string = "stamp " . $ct[$qt['datecreationcrit1']] . " :stamp";
                $where_array[":stamp"] = $mysqldate;
                $this->query_summary("date de création " . $ct[$qt['datecreationcrit1']] . " " . $qt['datecreation1']);

                if (trim($qt['datecreation2'])) {
                    $phpdate = strtotime(trim($qt['datecreation2']));
                    $mysqldate = date('Y-m-d H:i:s', $phpdate);
                    $where_string .= " AND stamp " . $ct[$qt['datecreationcrit2']] . " :stampi";
                    $where_array[":stampi"] = $mysqldate;
                    $this->query_summary(" et " . $ct[$qt['datecreationcrit2']] . " " . $qt['datecreation2']);
                } // datecreation2
            } // datecreation1
// Recherche d'après le créateur
            if (trim($qt['signaturecreation'])) {
                $s = trim($qt['signaturecreation']);
                if ($where_string != "")
                    $where_string .= " AND ";
                $where_string .= ' (user_id = :sc';
                $where_array[':sc'] = $s;
                $where_string .= " AND action = :actc) ";
                $where_array[':actc'] = 'Création';
                $this->query_summary("Signature de création = " . Utilisateur::model()->findByPk($s)->pseudo);
            }

// ---
// Préparation de la requête pour des recherche sur la modification
            if (trim($qt['datemodif1'])) {
                $phpdate = strtotime(trim($qt['datemodif1']));
                $mysqldate = date('Y-m-d H:i:s', $phpdate);
                if ($where_string != "")
                    $where_string .= " AND ";
                $where_string .= "stamp " . $ct[$qt['datemodifcrit1']] . " :stampii";
                $where_array[":stampii"] = $mysqldate;
                $this->query_summary("date de modification " . $ct[$qt['datemodifcrit1']] . " " . $qt['datemodif1']);


                if (trim($qt['datemodif2'])) {
                    $phpdate = strtotime(trim($qt['datemodif2']));
                    $mysqldate = date('Y-m-d H:i:s', $phpdate);
                    $where_string .= " AND stamp " . $ct[$qt['datemodifcrit2']] . " :stampiii";
                    $where_array[":stampiii"] = $mysqldate;
                    $this->query_summary(" et " . $ct[$qt['datemodifcrit2']] . " " . $qt['datemodif2']);
                } // datemodif2
            } // datemodif1
// Recherche d'après le modificateur
            if (trim($qt['signaturemodification'])) {
                $s = trim($qt['signaturemodification']);
                if ($where_string != "")
                    $where_string .= " AND ";
                $where_string .= ' (user_id = :sm';
                $where_array[':sm'] = $s;
                $where_string .= " AND action = :actm) ";
                $where_array[':actm'] = 'Modification';
                $this->query_summary("Signature de modification = " . Utilisateur::model()->findByPk($s)->pseudo);
            }

            $where_string .= " AND model = :model ";
            $where_array[':model'] = 'abonnement';
            $cmd = Yii::app()->db->createCommand()
                    ->selectDistinct('m.model_id')
                    ->from('modifications m')
                    ->where($where_string)
                    ->limit($limite)
                    ->order("stamp DESC");

            $perunilids = $cmd->queryAll(true, $where_array);
            $ids = join("','", array_map('current', $perunilids));
// Ajout de la liste des ids concerné par la modification
            $criteria->addCondition("t.perunilid IN ('$ids')");
        } // Modifications
// Recherche tous les champs
        if (trim($qt['all'])) {
            $this->q = $qt['all'];
            $this->journalSearch($criteria);
        }

// Recherche de tous les champs au format texte : "LIKE %$term%"
        $textfield = @array(
            'j.titre' => explode(" ", $qt['titre']),
            'j.soustitre' => explode(" ", $qt['soustitre']),
            'j.titre_abrege' => explode(" ", $qt['titreabrege']),
            'j.titre_variante' => explode(" ", $qt['variantetitre']),
            'j.faitsuitea' => explode(" ", $qt['faitsuitea']),
            'j.devient' => explode(" ", $qt['devient']),
            'ed.editeur' => explode(" ", $qt['editeur_txt']),
            'j.issnl' => $qt['issnl'],
            'j.issn' => $qt['issn'],
            'j.reroid' => $qt['reroid'],
            'j.nlmid' => $qt['nlmid'],
            'j.coden' => $qt['coden'],
            'j.doi' => $qt['doi'],
            'j.urn' => $qt['urn'],
            'j.url_rss' => $qt['rss'],
            't.url_site' => $qt['url'],
            't.editeur_code' => $qt['codeediteur'],
            't.acces_user' => $qt['user'],
            't.acces_pwd' => $qt['pwd'],
            't.package' => $qt['package'],
            't.no_abo' => $qt['no_abo'],
            't.etatcoll' => $qt['etatcoll'],
            't.cote' => $qt['cote'],
            't.commentaire_pro' => $qt['commentairepro'],
            't.commentaire_pub' => $qt['commentairepub'],
            'j.commentaire_pub' => $qt['commentairepub'],
            't.DEPRECATED_sujetsfm' => $qt['sujetsfm'],
            't.DEPRECATED_fmid' => $qt['fmid'],
            //'t.DEPRECATED_historique' => $sm_name,
            't.DEPRECATED_historique' => $qt['historique'],
        );
        foreach ($textfield as $column => $value) {
// Pour champs dont on fait un recherche terme à terme
            if (is_array($value) && count($value) > 0) {
                $query = new CDbCriteria();
                foreach ($value as $term) {
                    $term = trim($term);
                    if ($term) {
                        $query->addSearchCondition($column, $term);
                        $this->query_summary("$column LIKE %$term%");
                    }
                }
                $criteria->mergeWith($query);
            }
// Pour les champs dont la recherche ne porte que sur un seul terme
            else {
                $value = trim($value);
                if ($value) {
                    $criteria->addSearchCondition($column, $value);
                    $this->query_summary("$column LIKE %$value%");
                }
            }
        }

// Recherche exacte : "= $term"
        $exact_fields = @array(
            'j.openaccess' => $qt['openaccess'],
            'j.parution_terminee' => $qt['parution_terminee'],
            't.licence' => $qt['licence'],
            't.statutabo' => $qt['statutabo'],
            't.plateforme' => $qt['plateforme'],
            't.gestion' => $qt['gestion'],
            't.histabo' => $qt['histabo'],
            't.support' => $qt['support'],
            't.format' => $qt['format'],
            't.editeur' => $qt['editeur'],
            't.titreexclu' => $qt['titreexclu'],
            't.localisation' => $qt['localisation'],
            't.etatcoll_deba' => $qt['etatcolldeba'],
            't.etatcoll_debv' => $qt['etatcolldebv'],
            't.etatcoll_debf' => $qt['etatcolldebf'],
            't.etatcoll_fina' => $qt['etatcollfina'],
            't.etatcoll_finv' => $qt['etatcollfinv'],
            't.etatcoll_finf' => $qt['etatcollfinf'],
            't.acces_elec_gratuit' => $qt['acces_elec_gratuit'],
            't.acces_elec_chuv' => $qt['acces_elec_chuv'],
            't.acces_elec_unil' => $qt['acces_elec_unil'],
        );
        foreach ($exact_fields as $column => $value) {
            $value = trim($value);
            if ($value) {
                $criteria->addCondition("$column = '$value'");
                $this->query_summary("$column = $value");
            }
        }

// Traitement du cas de l'embargo
        if (trim($qt['embargo'])) {
            $criteria->addCondition("t.embargo_mois " . $ct[$qt['embargocrit']] . " '" . $qt['embargo'] . "'");
            $this->query_summary("t.embargo_mois " . $ct[$qt['embargocrit']] . " " . $qt['embargo']);
        }

        return $criteria;
        /*

          // S'il n'y auncun critère, on ne revoie rien.
          if (!$criteria->condition) {
          return NULL;
          } else {
          return new CActiveDataProvider(Abonnement::model(), array('criteria' => $criteria, 'pagination' => array(
          'pageSize' => $this->pagesize)));
          }

         */
    }

    public function query_summary($log) {
        if ($this->q_summary == "") {
            $this->q_summary = $log;
        } else {
            $this->q_summary .= ", " . $log;
        }
    }

    public function emptyQuerySummary() {
        $this->q_summary = "";
    }

    public function getQuerySummary() {
        return $this->q_summary;
    }

    /**
     * La fonction joinAbo ajoute les critères communs a beaucoup de recherches
     * dans la base PerUNIL :
     * - Jointure avec la table abonnement
     * - Ajouter les titres exclu seulement si l'utilisateur est authentifié
     * - Ajouter le critère du support désiré
     * - Trier par titre
     * @param CDbCriteria $criteria Requête en cours de construction, passage par référence
     */
    private function joinAbo($criteria) {
        $criteria->join .= 'INNER JOIN `abonnement` `abonnements` ON `abonnements`.`perunilid`=`t`.`perunilid` AND abonnements.perunilid IS NOT NULL ';

//$criteria->addCondition("abonnements.perunilid IS NOT NULL");
        if (Yii::app()->user->isGuest) {
// Si l'utilisateur n'est pas authentifié, on ne prend pas en compte
// les abonnements de titre exculs
//$criteria->addCondition("abonnements.titreexclu=0");
            $criteria->join .= " AND abonnements.titreexclu=0 ";
        } else {
// Si l'utilisateur est authentifié, on recherche si titreexcul n'est
// pas null
            if (isset($this->titreexclu)) {
//$criteria->addCondition("abonnements.titreexclu=$this->titreexclu");
                $criteria->join .= " AND abonnements.titreexclu=$this->titreexclu ";
            }
        }
        if ($this->support > 0) {
//$criteria->addCondition("abonnements.support=$this->support");
            $criteria->join .= " AND abonnements.support=$this->support ";
        }
        $criteria->order = "titre";
        $criteria->distinct = true;
    }

}
