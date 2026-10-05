import { Compass } from 'lucide-react'
import { Link } from 'react-router'
import { Button } from '@/components/ui/button'
import { EmptyState } from '@/components/ui/feedback'

export function NotFoundPage() {
  return (
    <div className="card mt-10">
      <EmptyState
        icon={Compass}
        title="Page not found"
        description="The page you are looking for does not exist or was moved."
        action={
          <Link to="/shipments">
            <Button variant="outline">Back to shipments</Button>
          </Link>
        }
      />
    </div>
  )
}
