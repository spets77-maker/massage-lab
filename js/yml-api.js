/**
 * Build API URL so it works without Apache rewrite:
 *   /api/index.php?r=auth%2Flogin
 * Use this for every fetch to /api/...
 */
function ymlApiUrl(path) {
  if (typeof path !== 'string' || path.indexOf('/api/') !== 0) {
    return path;
  }
  var rest = path.slice(5).replace(/^\/+/, '');
  return '/api/index.php?r=' + encodeURIComponent(rest);
}
