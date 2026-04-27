import { useState } from "react";
import { api } from "./api";

export const useInfiniteProducts = () => {
  const [products, setProducts] = useState([]);
  const [page, setPage] = useState(1);
  const [hasMore, setHasMore] = useState(true);
  const [loading, setLoading] = useState(false);

  const load = async (params = {}, reset = false) => {
    if (loading) return;

    try {
      setLoading(true);

      const nextPage = reset ? 1 : page;

      const query = new URLSearchParams({
        page: nextPage,
        limit: 30,
        ...params
      }).toString();

      const res = await api.get(`products?${query}`);

      const items = res.data.data;

      setProducts(prev =>
        reset ? items : [...prev, ...items]
      );

      setPage(nextPage + 1);
      setHasMore(res.data.pagination.hasMore);

    } finally {
      setLoading(false);
    }
  };

  return { products, load, hasMore, loading };
};