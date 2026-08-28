<?php

function post_uninstall()
{
    echo 'ZUGFeRD for AOS Invoices wurde deinstalliert.<br>';
    echo 'easybill/zugferd-php wird aus Sicherheitsgründen nicht automatisch per Composer entfernt.<br>';
    echo 'Bereits erzeugte PDF/XML-Dateien im upload/zugferd-Verzeichnis werden nicht gelöscht.<br>';
}
