<?php
$support = Yii::app()->session['search']->support;
// Recherche des abonnement disponibles
if (!$data instanceof Journal) {
    $data = Journal::model()->findByPk($data['perunilid']);
}
if (Yii::app()->user->isGuest) {
    if ($support == 1) {
        $abos = $data->activeElecAbos;
    } elseif ($support == 2) {
        $abos = $data->activePaperAbos;
    } else {
        $abos = $data->activeAllAbos;
    }
} else { // Admin : tous les abonnement, même inactifs
    if ($support == 1) {
        $abos = $data->ElecAbos;
    } elseif ($support == 2) {
        $abos = $data->PaperAbos;
    } else {
        $abos = $data->AllAbos;
    }
}

// Classement des abonnement en fonction du support papier ou électronique
usort($abos, array("Abonnement", "compare"));

if ($index % 2 == 0) {
    $stripClass = ' class="active" ';
} else {
    $stripClass = '';
}

// On affiche pas les titre qui ne possèdent aucun abonnement actif.
$nbabo = count($abos);
if ($nbabo == 0 && Yii::app()->user->isGuest) {
    return;
} elseif ($nbabo == 0) {
    ?>
    <tr <?php echo $stripClass; ?> >
    <?php if ($i == 0) : ?>
            <td><!-- Titre -->

                <strong>
        <?php echo ucfirst($data->titre); ?>
                </strong>
                    <?php
                    echo "<br>";
                    if ($data->soustitre) {
                        echo "<small>";

                        if ($data->soustitre != "") {
                            if (strlen($data->soustitre) >= 80) {
                                echo CHtml::encode(substr($data->soustitre, 0, 80) . " ... ");
                            } else {
                                echo CHtml::encode($data->soustitre);
                            }
                        }

                        echo "</small>";
                    }
                    ?>
            </td>
            <?php endif; ?>
        <td colspan="3"><!-- Aucun abonnement -->
            Aucun abonnement
        </td>
        <td colspan="2"><!-- Commandes -->
            <?php
            echo CHtml::htmlButton('<span class="glyphicon glyphicon-search"></span> Détail', array(
                'onclick' => 'js:document.location.href="' . Yii::app()->createUrl("site/detail", array("id" => $data->perunilid)) . '"',
                'class' => "btn btn-default  btn-xs"));

            if (!Yii::app()->user->isGuest) {
                echo '&nbsp;';
                echo CHtml::htmlButton('<span class="glyphicon glyphicon-pencil"></span> Editer', array(
                    'onclick' => 'js:document.location.href="' . Yii::app()->createUrl('admin/peredit/', array('perunilid' => $data->perunilid)) . '"',
                    'class' => "btn btn-primary btn-xs"));
            }
            ?>
        </td>
    </tr>
        <?php
    }


    foreach ($abos as $i => $abo) :
        ?>
    <tr <?php echo $stripClass; ?> >
    <?php if ($i == 0) : ?>
            <td rowspan="<?php echo $nbabo; ?>" ><!-- Titre -->

                <strong>
        <?php echo ucfirst($data->titre); ?>
                </strong>
                    <?php
                    echo "<br>";
                    if ($data->soustitre) {
                        echo "<small>";

                        if ($data->soustitre != "") {
                            if (strlen($data->soustitre) >= 80) {
                                echo CHtml::encode(substr($data->soustitre, 0, 80) . " ... ");
                            } else {
                                echo CHtml::encode($data->soustitre);
                            }
                        }

                        echo "</small>";
                    }
                    ?>
            </td>
            <?php endif; ?>
        <td><!-- Lien -->
        <?php
        echo $abo->htmlImgTag();
        echo $abo->htmlImgTitreExclu();
        $this->widget('AboUrlWidget', array('abo' => $abo, 'jrn' => $data));
        ?>
        </td> 
        <td> <!-- Etatcoll -->
            <?
            // Etat de la collection
            echo CHtml::encode($abo->etatcoll);
            ?>
        </td>
        <td> <!-- Palteforme / Localisation -->
            <?php
            if ($abo->papier) {
                if (isset($abo->localisation0)) {
                    echo CHtml::encode($abo->localisation0->localisation);
                    if (isset($abo->cote) && $abo->cote != "") {
                        echo " <small>[cote : {$abo->cote}]</small>";
                    }
                }
            } else { // électronique
                if (isset($abo->plateforme0) && !empty($abo->plateforme0->plateforme)) {
                    echo $abo->plateforme0->plateforme;
                }
                if (!empty($data->openaccess)) {
                    ?>
                    <img  style="float:right" src="<?= Yii::app()->baseUrl; ?>/images/open-access-logo_16.png"/>
                    <?php
                }
            }
            ?>
        </td>
        <td><!-- Commandes -->
            <?php
            echo CHtml::htmlButton('<span class="glyphicon glyphicon-search"></span> Détail', array(
                'onclick' => 'js:document.location.href="' . Yii::app()->createUrl("site/detail", array("id" => $data->perunilid)) . '"',
                'class' => "btn btn-default  btn-xs"));

            if (!Yii::app()->user->isGuest) {
                echo '<br>&nbsp;';
                echo CHtml::htmlButton('<span class="glyphicon glyphicon-pencil"></span> Editer', array(
                    'onclick' => 'js:document.location.href="' . Yii::app()->createUrl('admin/peredit/', array('perunilid' => $data->perunilid)) . '"',
                    'class' => "btn btn-primary btn-xs"));
            }
            ?>
        </td>
            <?php
            // Commandes d'administration
            if (!Yii::app()->user->isGuest) :
                echo "<td class='info'><!-- Fusion -->";
                // Fusion
                echo CHTml::checkBox("perunilid[$data->perunilid]", false, array('value' => $data->perunilid));
                echo "<small>" . CHtml::label("Fusion", $data->perunilid) . "</small>";

                echo "<br>";

                // Modèle pour la fusion
                echo CHtml::radioButton('maitre', false, array('value' => $data->perunilid));
                echo "<small>" . CHtml::label("Modèle", 'fusion' . $data->perunilid) . "</small>";

                echo "<br>";

                echo CHtml::submitButton('Fusion !', array('class' => "btn btn-default btn-xs",
                    'form' => "fusionform"));
                echo "</td>";
            endif;
            ?>   
    </tr>
        <?php
    endforeach;
    ?>