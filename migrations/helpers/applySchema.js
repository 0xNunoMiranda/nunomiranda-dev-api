const fs = require('fs');
const path = require('path');

module.exports = async function applySchema(knex, fileName) {
  const filePath = path.resolve(__dirname, '..', '..', 'sql', fileName);
  const rawSql = fs.readFileSync(filePath, 'utf8');
  const blocks = rawSql.split(/DELIMITER \$\$/i);

  for (const block of blocks) {
    const trimmed = block.trim();
    if (!trimmed) continue;

    if (trimmed.includes('CREATE PROCEDURE') || trimmed.includes('DROP PROCEDURE')) {
      const segments = trimmed.split('$$');
      for (const segment of segments) {
        const stmt = segment.replace(/DELIMITER ;/gi, '').trim();
        if (stmt) {
          await knex.raw(stmt);
        }
      }
    } else {
      const statements = trimmed
        .split(/;\s*(?=CREATE|DROP|CALL|INSERT|UPDATE|SELECT|$)/i)
        .map((stmt) => stmt.trim())
        .filter((stmt) => stmt.length > 0);

      for (const statement of statements) {
        await knex.raw(statement);
      }
    }
  }
};
