const selects = document.querySelectorAll('select');
for (const select of selects) {
select.onchange = (function (onchange) {
  return function(evt) {
  // reference to event to pass argument properly
  evt  = evt || event;
  // if an existing event already existed then execute it.
  if (onchange) { onchange(evt); }
  var text = $(this).find('option:selected').text()
  var $aux = $('<select/>').append($('<option/>').text(text))
  $(this).after($aux)
  $(this).width($aux.width())
  $aux.remove()
  }
})(select.onchange);
}
