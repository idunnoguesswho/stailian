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
    const row = payload.row || {};

    if (!ALLOWED_TABLES.has(table)) {
      return jsonResponse({ ok: false, error: 'Table is not writable: ' + table });
    }

    const spreadsheet = SpreadsheetApp.openById(SPREADSHEET_ID);
    const sheet = spreadsheet.getSheetByName(table);
    if (!sheet) return jsonResponse({ ok: false, error: 'Sheet not found: ' + table });

    const headers = sheet.getRange(1, 1, 1, sheet.getLastColumn()).getValues()[0];
    const values = headers.map(header => row[header] ?? '');
    sheet.appendRow(values);

    return jsonResponse({ ok: true, table, appended: values.length });
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
