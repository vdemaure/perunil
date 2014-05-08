<?php

class SiteController extends Controller {

    public $support;
    public $field;

    /**
     * Declares class-based actions.
     */
    public function actions() {
        return array(
            // captcha action renders the CAPTCHA image displayed on the contact page
            'captcha' => array(
                'class' => 'CCaptchaAction',
                'backColor' => 0xFFFFFF,
            ),
            // page action renders "static" pages stored under 'protected/views/site/pages'
            // They can be accessed via: index.php?r=site/page&view=FileName
            'page' => array(
                'class' => 'CViewAction',
            ),
        );
    }

    /**
     * Action d'entrée dans PérUnil : la recherche simple. 
     */
    public function actionIndex() {
        $this->activate_session_search_component();

        // Affichage du formulaire de recherche et év. des résultats.
        $this->render('simpleSearch');
    }

    public function actionSimpleSearchResults($typeAffichage = NULL) {
        Yii::app()->session['searchtype'] = 'simple';
        $this->activate_session_search_component();

        $search_done = isset($_GET['q']) && trim($_GET['q']) != "";

        // Si une nouvelle recherche a été effectuée
        if ($search_done) {
            Yii::app()->session['search']->support = isset($_GET['support']) ? $_GET['support'] : '0';
            Yii::app()->session['search']->search_type = isset($_GET['field']) ? $_GET['field'] : SearchComponent::TWORDS;
            Yii::app()->session['search']->maxresults = isset($_GET['maxresults']) ? $_GET['maxresults'] : '-1'; // infini par défaut
            Yii::app()->session['search']->depotlegal = filter_input(INPUT_GET,'depotlegal',FILTER_VALIDATE_BOOLEAN);//isset($_GET['depotlegal']);
            Yii::app()->session['search']->simple_query_str = $_GET['q'];
        }
        // Si une recherche a été sauvegardée
        if (isset(Yii::app()->session['search']->simple_query_str)) {
            $search_done = true;
        }

        if ($typeAffichage == NULL && empty(Yii::app()->session['typeAffichage'])){
            Yii::app()->session['typeAffichage'] = 1;
        }
        if ($typeAffichage != NULL) {
            Yii::app()->session['typeAffichage'] = $typeAffichage;
        }

        if (Yii::app()->session['typeAffichage'] == 1){
            $this->render('searchResults', array('search_done' => $search_done, 'searchtype' => 'simple'));
        }
        else{
            $this->render('searchResults_tab', array('search_done' => $search_done, 'searchtype' => 'simple'));
        }
        
        // Affichage des résultats.
        //$this->render('searchResults_tab', array('search_done' => $search_done, 'searchtype' => 'simple'));
    }

    public function actionSimpleclean() {
        unset(Yii::app()->session['depotlegal']);
        unset(Yii::app()->session['search']);
        $this->redirect($this->createUrl("site/index"));
    }

    /**
     * Recherche avancée 
     */
    public function actionAdvSearchResults() {

        Yii::app()->session['searchtype'] = 'adv';
        $this->activate_session_search_component();

        $search_done = isset($_GET['advsearch']) && trim($_GET['advsearch']) == "advsearch";

        // Si une nouvelle recherche a été effectuée
        if ($search_done) {
            Yii::app()->session['search']->support = isset($_GET['support']) ? $_GET['support'] : '0';
            Yii::app()->session['search']->depotlegal = isset($_GET['depotlegal']);
            Yii::app()->session['search']->search_type = isset($_GET['field']) ? $_GET['field'] : SearchComponent::TWORDS;
            Yii::app()->session['search']->adv_query_tab = $_GET;
        }

        // Si une recherche a été sauvegardée
        if (isset(Yii::app()->session['search']->adv_query_tab)) {
            $search_done = true;
        }
        // affichage de la recherche avancée.
        //$render_params['advsearch'] = true;  
        // Affichage des résultats.
        $this->render('searchResults', array('search_done' => $search_done, 'searchtype' => 'adv'));
    }

    public function actionAdvSearch() {
        $this->activate_session_search_component();
        $this->render('advSearch');
    }

    public function actionAdvclean() {
        unset(Yii::app()->session['search']);
        $this->redirect($this->createUrl("site/advSearch"));
    }

    public function actionSujet() {
        $this->render('sujet');
    }

    /**
     * This is the action to handle external exceptions.
     */
    public function actionError() {
        if ($error = Yii::app()->errorHandler->error) {
            if (Yii::app()->request->isAjaxRequest)
                echo $error['message'];
            else
                $this->render('error', $error);
        }
    }

    /**
     * Displays the contact page
     */
    public function actionContact() {
        $model = new ContactForm;
        if (isset($_POST['ContactForm'])) {
            $model->attributes = $_POST['ContactForm'];
            if ($model->validate()) {
                $headers = "From: {$model->email}\r\nReply-To: {$model->email}";
                mail(Yii::app()->params['adminEmail'], $model->subject, $model->body, $headers);
                Yii::app()->user->setFlash('contact', 'Thank you for contacting us. We will respond to you as soon as possible.');
                $this->refresh();
            }
        }
        $this->render('contact', array('model' => $model));
    }

    /**
     * Displays the login page
     */
    public function actionLogin() {
        $model = new LoginForm;

        // if it is ajax validation request
        if (isset($_POST['ajax']) && $_POST['ajax'] === 'login-form') {
            echo CActiveForm::validate($model);
            Yii::app()->end();
        }

        // collect user input data
        if (isset($_POST['LoginForm'])) {
            $model->attributes = $_POST['LoginForm'];
            // validate user input and redirect to the previous page if valid
            if ($model->validate() && $model->login())
                $this->redirect(CController::createUrl('/admin/index'));
        }
        // display the login form
        $this->render('login', array('model' => $model));
    }

    /**
     * Logs out the current user and redirect to homepage.
     */
    public function actionLogout() {
        Yii::app()->user->logout();
        $this->redirect(Yii::app()->homeUrl);
    }

    public function actionDetail($id, $activeTab = false, $dialogue = false) {
        $model = Journal::model()->findByPk($id);

        if ($dialogue) {
            // la vue en détail est appelée depuis un popup javascript
            //$this->layout = "dialogue";
            $this->layout = false;

            $this->render('detail', array(
                'model' => $model,
                'activeTab' => $activeTab,
                'dialogue' => true,
            ));
        } else {
            // Affichage normal de la vue en détail
            $this->render('detail', array(
                'model' => $model,
                'activeTab' => $activeTab,
            ));
        }
    }

 

    //
// Function pour nettoyer les critères de recherche (mots vides, ponctuation...)
//
    private function clean_search($original) {
        $var = trim($original);
        $var = " " . $var . " ";
        $var = str_ireplace(",", "", $var);
        $var = str_ireplace(". ", " ", $var);
        $var = str_ireplace(": ", " ", $var);
        $var = str_ireplace(":", " ", $var);
        $var = str_ireplace("-", " ", $var);
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
        return trim($var);
    }

}
