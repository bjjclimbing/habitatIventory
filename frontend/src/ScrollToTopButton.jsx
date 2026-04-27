import { useEffect, useState } from "react";

export default function ScrollToTopButton() {
  const [progress, setProgress] = useState(0);

  useEffect(() => {
    const handleScroll = () => {
      const scrollTop = window.scrollY;
      const height =
        document.documentElement.scrollHeight -
        document.documentElement.clientHeight;

      const scrolled = (scrollTop / height) * 100;
      setProgress(scrolled);
    };

    window.addEventListener("scroll", handleScroll);
    return () => window.removeEventListener("scroll", handleScroll);
  }, []);

  const scrollToTop = () => {
    window.scrollTo({
      top: 0,
      behavior: "smooth",
    });
  };

  // círculo SVG
  const radius = 22;
  const circumference = 2 * Math.PI * radius;
  const offset = circumference - (progress / 100) * circumference;

  return (
    <div className="fixed bottom-6 right-6 z-50">

      <button
        onClick={scrollToTop}
        className="relative w-14 h-14 flex items-center justify-center bg-white rounded-full shadow-lg hover:scale-105 transition"
      >

        {/* SVG PROGRESS */}
        <svg
          className="absolute top-0 left-0 w-full h-full rotate-[-90deg]"
        >
          {/* fondo */}
          <circle
            cx="28"
            cy="28"
            r={radius}
            stroke="#e5e7eb"
            strokeWidth="4"
            fill="none"
          />

          {/* progreso */}
          <circle
            cx="28"
            cy="28"
            r={radius}
            stroke="#2563eb"
            strokeWidth="4"
            fill="none"
            strokeDasharray={circumference}
            strokeDashoffset={offset}
            strokeLinecap="round"
          />
        </svg>

        {/* ICONO */}
        <span className="text-blue-600 text-lg font-bold z-10">
          ↑
        </span>

      </button>
    </div>
  );
}