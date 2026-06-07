const SPREADSHEET_ID = '1VWosrMFHAl5WtzonzgaT-3B9bHU0mPFJeIS0FWV86KU';
const ALLOWED_TABLES = new Set([
  'rolldata',
  'characters',
  'inventory',
  'battle_log'
]);

function doPost(e) {
  try {
    const payload = JSON.parse(e.postData.contents || '{}');
    const table = String(payload.table || '');
    const op = String(payload.op || 'append');

    if (!ALLOWED_TABLES.has(table)) {
      return jsonResponse({ ok: false, error: 'Table is not writable: ' + table });
    }

    const spreadsheet = SpreadsheetApp.openById(SPREADSHEET_ID);
    const sheet = spreadsheet.getSheetByName(table);
    if (!sheet) return jsonResponse({ ok: false, error: 'Sheet not found: ' + table });

    const headers = getHeaders(sheet);
    let result;
    if (op === 'append') {
      result = appendObjectRow(sheet, headers, payload.row || {});
    } else if (op === 'update' || op === 'upsert') {
      result = upsertObjectRow(sheet, headers, payload.key || {}, payload.row || {}, op === 'upsert');
    } else if (op === 'batchUpsert') {
      const keyFields = payload.keyFields || ['id'];
      const rows = payload.rows || [];
      result = rows.map(function(row) {
        const key = {};
        keyFields.forEach(function(field) { key[field] = row[field]; });
        return upsertObjectRow(sheet, headers, key, row, true);
      });
    } else {
      return jsonResponse({ ok: false, error: 'Unsupported op: ' + op });
    }

    return jsonResponse({ ok: true, table: table, op: op, result: result });
  } catch (err) {
    return jsonResponse({ ok: false, error: String(err) });
  }
}

function doGet() {
  return jsonResponse({ ok: true, service: 'Stailian Sheet write endpoint' });
}

function jsonResponse(value) {
  return ContentService
    .createTextOutput(JSON.stringify(value))
    .setMimeType(ContentService.MimeType.JSON);
}

function getHeaders(sheet) {
  return sheet.getRange(1, 1, 1, sheet.getLastColumn()).getValues()[0].map(String);
}

function appendObjectRow(sheet, headers, row) {
  const values = headers.map(function(header) { return row[header] != null ? row[header] : ''; });
  sheet.appendRow(values);
  return { appended: values.length };
}

function upsertObjectRow(sheet, headers, key, row, allowInsert) {
  const rowIndex = findRowIndex(sheet, headers, key);
  if (!rowIndex) {
    if (!allowInsert) return { updated: false, missing: true };
    appendObjectRow(sheet, headers, row);
    return { inserted: true };
  }

  const values = sheet.getRange(rowIndex, 1, 1, headers.length).getValues()[0];
  headers.forEach(function(header, index) {
    if (row[header] != null) values[index] = row[header];
  });
  sheet.getRange(rowIndex, 1, 1, headers.length).setValues([values]);
  return { updated: true, rowIndex: rowIndex };
}

function findRowIndex(sheet, headers, key) {
  const keyFields = Object.keys(key || {});
  if (!keyFields.length) return null;
  const lastRow = sheet.getLastRow();
  if (lastRow < 2) return null;
  const values = sheet.getRange(2, 1, lastRow - 1, headers.length).getValues();
  for (var rowOffset = 0; rowOffset < values.length; rowOffset++) {
    var matched = true;
    for (var i = 0; i < keyFields.length; i++) {
      var field = keyFields[i];
      var col = headers.indexOf(field);
      if (col < 0 || String(values[rowOffset][col]) !== String(key[field])) {
        matched = false;
        break;
      }
    }
    if (matched) return rowOffset + 2;
  }
  return null;
}
