import { OkPacket, ResultSetHeader, RowDataPacket } from 'mysql2';
import pool from '../db';

type ProcedureResult = RowDataPacket[][] | RowDataPacket[] | OkPacket | OkPacket[] | ResultSetHeader;

const isOkPacketArray = (value: unknown): value is OkPacket[] =>
  Array.isArray(value) && value.length > 0 && Object.prototype.hasOwnProperty.call(value[0], 'affectedRows');

export const callProcedure = async <TRow extends RowDataPacket>(sql: string, params: unknown[]) => {
  const [resultSets] = await pool.query<ProcedureResult>(sql, params);
  const sets = Array.isArray(resultSets) ? resultSets : [resultSets];
  for (let i = sets.length - 1; i >= 0; i -= 1) {
    const rows = sets[i];
    if (Array.isArray(rows) && !isOkPacketArray(rows)) {
      return rows as TRow[];
    }
  }
  return [] as TRow[];
};
