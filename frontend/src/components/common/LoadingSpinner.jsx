function LoadingSpinner({ size = 'large', className = '' }) {
  const sizeClasses = {
    small: 'h-4 w-4',
    medium: 'h-8 w-8',
    large: 'h-12 w-12',
  };

  if (size === 'large') {
    return (
      <div className="min-h-screen flex items-center justify-center bg-gray-100">
        <div className="text-center">
          <div
            className={`${sizeClasses[size]} animate-spin rounded-full border-4 border-primary-200 border-t-primary-600 mx-auto`}
          />
          <p className="mt-4 text-gray-600">Loading...</p>
        </div>
      </div>
    );
  }

  return (
    <div
      className={`${sizeClasses[size]} animate-spin rounded-full border-2 border-gray-200 border-t-primary-600 ${className}`}
    />
  );
}

export default LoadingSpinner;
