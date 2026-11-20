var pi = null;
    
function addOption(value) {
    
    if ($('#ccm-ausstattung-optionValue').val().length == 0) {
        alert("Fehler: Es darf kein leeres String übergeben werden!");
        return false;
    }

    item_list.push({"id": currentOption, "name": $('#ccm-ausstattung-optionValue').val(), "comment": "", "new": true});

    currentOption++;

    $("#ccm-ausstattung-optionValue").val("");

    $("#austattungsListe").sortable("refresh");

    BuildItemlist();

    return true;
}

function removeOption(id) {
    opt = $("#option" + id).get(0);
    
    pi.removeChild(opt);
    // currentOption--;
    if (currentOption === 0) {
        pi.innerHTML = "None";
    }
}