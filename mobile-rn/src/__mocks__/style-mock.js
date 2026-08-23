// CSS imports (src/global.css, web-only CSS custom properties) have no
// meaning under Jest's Node test environment - only Metro's web bundler
// processes them. Stubbed to an empty module so files that import CSS
// for its web-only side effect (see src/constants/theme.ts) can still
// be required in tests targeting native behavior.
module.exports = {};
