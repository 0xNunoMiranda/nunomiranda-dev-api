export const success = <T>(data?: T) => ({ ok: true, data });

export const failure = (code: string, message: string) => ({
  ok: false,
  error: { code, message },
});
