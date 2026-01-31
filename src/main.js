// Diese Funktion wird beim Klick auf ein <td> aufgerufen
function handleTdClick(event) {
  // Lese den Wert aus dem data-Attribut des geklickten <td>
  var selectedValue = event.currentTarget.getAttribute('data-value');
  if (selectedValue) {
    // Hole das <select>-Element per ID
    const selectElement = document.getElementById('fselect');
    // Setze den Wert des Selects
    selectElement.value = selectedValue;
    // Optional: Triggern des change-Events, falls benötigt
    var eventChange = new Event('change');
    selectElement.dispatchEvent(eventChange);
  }
}

function togglePublic() {
    const checkbox = document.getElementById("fpublic");
    const textInput = document.getElementById("ftitle");
    textInput.disabled = !checkbox.checked;
}

const script = document.createElement('script');
script.src = './functions/formhandler.js';
document.head.appendChild(script);

window.addEventListener('DOMContentLoaded', function() {
  // Alle <td>-Elemente, die ein data-value-Attribut besitzen, auswählen
  document.querySelectorAll('td[data-value]').forEach(function(td) {
    td.addEventListener('click', handleTdClick);
  });
  initFormHandler();
});
