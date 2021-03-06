<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en" lang="en">
    <head>
        <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
        <meta name="language" content="en" />

        <!-- blueprint CSS framework -->
        <link rel="stylesheet" type="text/css" href="<?php echo Yii::app()->request->baseUrl; ?>/css/screen.css" media="screen, projection" />
        <link rel="stylesheet" type="text/css" href="<?php echo Yii::app()->request->baseUrl; ?>/css/print.css" media="print" />

        <link rel="stylesheet" type="text/css" href="<?php echo Yii::app()->request->baseUrl; ?>/css/main.css" />
        <link rel="stylesheet" type="text/css" href="<?php echo Yii::app()->request->baseUrl; ?>/css/form.css" />
        <link rel="stylesheet" type="text/css" href="<?php echo Yii::app()->request->baseUrl; ?>/css/tabs.css" />
        <!--[if IE]>
        <link rel="stylesheet" type="text/css" href="<?php echo Yii::app()->request->baseUrl; ?>/css/ie.css" media="screen, projection" />
        <![endif]-->
        <title><?php echo CHtml::encode($this->pageTitle); ?></title>
        
        
        
        <?php 
        // Ajout de la librairie javascript jquery
        Yii::app()->clientScript->registerCoreScript('jquery'); 
        Yii::app()->clientScript->registerCoreScript('jquery.ui'); 

        // ajout de la css de jquery ui
        Yii::app()->clientScript->registerCssFile(
            Yii::app()->clientScript->getCoreScriptUrl().
                '/jui/css/base/jquery-ui.css'
        );
        
        
        // Autocompletion lors de la recherche
        //Yii::app()->clientScript->registerCoreScript('autocomplete'); 
        
        ?>

        
    </head>

    <body>

        <div class="container" id="page">

            <div id="header">
                <div id="logo">
                    <a href ="<?= Yii::app()->baseUrl; ?>">
                        <img src="<?= Yii::app()->baseUrl; ?>/images/logo_perunil.png"/>
                        <span id="slogan">Recherche de périodiques disponibles à l'UNIL et au CHUV</span>
                    </a>

                </div>

            </div><!-- header -->

            <div id="mainmenu">
                <?php
                $this->widget('zii.widgets.CMenu', array(
                    'items' => array(
                        array('label' => 'Recherche simple', 'url' => array('/site/index')),
                        array('label' => 'Recherche avancée','url' => array('/site/adv')),
                        array('label' => 'Sujets',           'url' => array('/site/sujet')),
                        array('label' => 'Aide',             'url' => array('/site/page', 'view' => 'aide'), 'visible' => Yii::app()->user->isGuest),
                        array('label' => 'Contact',          'url' => array('/site/contact'),                 'visible' => Yii::app()->user->isGuest),
                        array('label' => 'Login',            'url' => array('/site/login'),                   'visible' => Yii::app()->user->isGuest),
                        array('label' => 'Logout ('.Yii::app()->user->name.')','url' => array('/site/logout'),'visible' => !Yii::app()->user->isGuest),
                    ),
                    'lastItemCssClass' => 'right',
                ));
                ?>
            </div>
            <?php
            if (!Yii::app()->user->isGuest) {
                echo '<div id="adminmenu"> ';
                $this->widget('zii.widgets.CMenu', array(
                    'items' => array(
                        array('label' => 'Accueil administration',  'url' => array('/admin/index')),
                        array('label' => 'Recherche admin','url' => array('/admin/search')),
                        array('label' => 'Nouveau périodique',   'url' => array('/admin/peredit')),
                        array('label' => 'Gérer les sujets',        'url' => array('/sujet/admin')),
                        array('label' => 'Gérer les listes',        'url' => array('/smalllist')),
                        array('label' => 'Suivit des modifications','url' => array('/admin/modifications'))
                    ),
                ));
                echo '</div>';
            }
            ?>

            <!-- mainmenu -->
            <?php if (isset($this->breadcrumbs)): ?>
                <?php
                $this->widget('zii.widgets.CBreadcrumbs', array(
                    'links' => $this->breadcrumbs,
                ));
                ?><!-- breadcrumbs -->
            <?php endif ?>

<?php echo $content; ?>

            <div class="clear"></div>

            <div id="footer">
                <div id="vd"> &nbsp; </div>
                <div id="swissu">
                    <a href="http://www.swissuniversity.ch">
                        <img alt="Swiss University" src="http://www.unil.ch/cms/images/logos/swissuniversity_blue_smush.png" border="0" height="17" width="136">
                    </a>
                </div>
                <div id="logobottom">
                    <a href="http://www.vd.ch">
                        <img border="0" alt="Canton de Vaud" src="http://www.unil.ch/cms/images/logos/vd_gray.gif">
                    </a>
                    <a class="liens" href="http://www.unil.ch">
                        <img border="0" alt="UNIL" src="http://serval.unil.ch/img/unilogo_noir.png">
                    </a>
                    <a class="liens" href="http://www.unil.ch/bcu">
                        <img border="0" alt="BCU Lausanne" src="http://www.unil.ch/webdav/site/serval/users/siteadmin/public/logo_bcu_gris.gif">
                    </a>
                    <a class="liens" href="http://www.chuv.ch">
                        <img border="0" alt="CHUV" src="http://serval.unil.ch/img/logo_chuv_transp_bleu.png">
                    </a>
                </div>
                <div id="linksbottom">
                    <?php echo CHtml::mailto('Contact', 'wwwperun@unil.ch'); ?> &nbsp;-&nbsp;
<?php echo CHtml::link('Copyright', 'http://www.unil.ch/central/page2200.html'); ?> &nbsp;-&nbsp;
<?php echo CHtml::link('Impressum', '/site/pages/impressum'); ?> &nbsp;-&nbsp;
    <?php if(Yii::app()->user->isGuest) {
        echo CHtml::link('Login', array('/site/login'), array('visible' => Yii::app()->user->isGuest));
    } else {
         echo CHtml::link('Logout (' . Yii::app()->user->name . ')', array('/site/logout'), array('visible' => !Yii::app()->user->isGuest));
    }
    ?> &nbsp;
                </div>
            </div><!-- footer -->

        </div><!-- page -->
        <div id="postaddress"> 
            Bibliothèque Universitaire de Médecine&nbsp;&nbsp;-&nbsp;
            CHUV BH08 - Bugnon 46&nbsp;-&nbsp;
            CH-1011 Lausanne&nbsp;
        </div>

    </body>
</html>
