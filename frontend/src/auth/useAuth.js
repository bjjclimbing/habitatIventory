import { useContext } from "react";
import { AuthContext } from "./AuthContext";

function parseJwt(token) {
  try {
    return JSON.parse(atob(token.split(".")[1]));
  } catch {
    return null;
  }
}

export const useAuth = () => {
  const context = useContext(AuthContext);

  const user = context.token ? parseJwt(context.token) : null;

  return {
    ...context,
    user,
  };
};