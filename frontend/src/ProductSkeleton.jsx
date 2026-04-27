export default function ProductSkeleton() {
    return (
      <tr className="border-b animate-pulse">
        <td className="py-3">
          <div className="h-4 bg-gray-300 rounded w-40"></div>
        </td>
  
        <td>
          <div className="h-4 bg-gray-300 rounded w-24"></div>
        </td>
  
        <td>
          <div className="h-4 bg-gray-300 rounded w-12"></div>
        </td>
  
        <td>
          <div className="h-4 bg-gray-300 rounded w-12"></div>
        </td>
  
        <td>
          <div className="h-4 bg-gray-300 rounded w-20"></div>
        </td>
      </tr>
    );
  }