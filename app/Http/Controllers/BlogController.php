<?php

namespace App\Http\Controllers;

use App\Models\Post;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class BlogController extends Controller
{
    public function gallery()
    {
        $images = Post::where('type', 'gallery')
            ->where('active', true)
            ->latest()
            ->get();

        return response()->json(['data' => $images]);
    }

    public function posts()
    {
        $posts = Post::where('type', 'article')
            ->where('active', true)
            ->latest()
            ->get()
            ->filter(fn (Post $post) => !$this->isCityActivityPost($post))
            ->values();

        return response()->json(['data' => $posts]);
    }

    public function cityActivities()
    {
        $activities = Post::where('type', 'article')
            ->where('active', true)
            ->latest()
            ->get()
            ->filter(fn (Post $post) => $this->isCityActivityPost($post))
            ->values();

        return response()->json(['data' => $activities]);
    }

    public function store(Request $request)
    {
        $val = $request->validate([
            'type' => 'required|in:gallery,article',
            'category' => ['nullable', 'string', Rule::in(['city-tour', 'ouikenac', 'event', 'city-activity'])],
            'title' => 'required|string',
            'image' => 'required|image|max:5120',
            'content' => 'nullable|required_if:type,article',
            'excerpt' => 'nullable|string',
            'location' => 'nullable|string',
        ]);

        $meta = $this->extractActivityMetadata($val['content'] ?? null);
        if ($meta) {
            // Activite autonome: aucune liaison obligatoire a un city-tour.
            $val['type'] = 'article';
            $val['category'] = 'city-activity';
        } elseif (($val['category'] ?? null) === 'city-activity') {
            return response()->json([
                'message' => 'Contenu activite invalide. Le JSON doit contenir kind=city-activity, price, currency, available_spots, schedule et departure_point.',
            ], 422);
        } elseif (empty($val['category'])) {
            return response()->json([
                'message' => 'Le champ category est requis pour les contenus non-activites.',
            ], 422);
        }

        try {
            if ($request->hasFile('image')) {
                $imageFile = $request->file('image');
                $folderName = 'blog';
                $destinationPath = storage_path("app/public/{$folderName}");
                $fileName = $imageFile->hashName();

                if (!file_exists($destinationPath)) {
                    if (!mkdir($destinationPath, 0775, true)) {
                        throw new Exception('Impossible de creer le dossier blog');
                    }
                }

                if ($imageFile->move($destinationPath, $fileName)) {
                    $val['image'] = "{$folderName}/{$fileName}";
                } else {
                    throw new Exception('Echec upload image');
                }
            }

            $post = Post::create($val);

            return response()->json(['message' => 'Cree avec succes', 'data' => $post]);
        } catch (Exception $e) {
            if (isset($val['image'])) {
                @unlink(storage_path("app/public/{$val['image']}"));
            }

            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function update(Request $request, $id)
    {
        $post = Post::findOrFail($id);

        $val = $request->validate([
            'type' => 'sometimes|in:gallery,article',
            'category' => ['sometimes', 'nullable', 'string', Rule::in(['city-tour', 'ouikenac', 'event', 'city-activity'])],
            'title' => 'sometimes|string',
            'content' => 'nullable|string',
            'excerpt' => 'nullable|string',
            'location' => 'nullable|string',
            'image' => 'nullable|image|max:5120',
            'active' => 'nullable|boolean',
        ]);

        $nextContent = array_key_exists('content', $val) ? $val['content'] : $post->content;
        $nextCategory = array_key_exists('category', $val) ? $val['category'] : $post->category;

        $meta = $this->extractActivityMetadata($nextContent);
        if ($meta) {
            // Activite autonome: pas de lien city-tour impose.
            $val['type'] = 'article';
            $val['category'] = 'city-activity';
        } elseif ($nextCategory === 'city-activity') {
            return response()->json([
                'message' => 'Contenu activite invalide. Le JSON doit contenir kind=city-activity, price, currency, available_spots, schedule et departure_point.',
            ], 422);
        }

        try {
            if ($request->hasFile('image')) {
                if ($post->image) {
                    $old = storage_path("app/public/{$post->image}");
                    if (file_exists($old)) {
                        @unlink($old);
                    }
                }

                $imageFile = $request->file('image');
                $folderName = 'blog';
                $destinationPath = storage_path("app/public/{$folderName}");
                $fileName = $imageFile->hashName();

                if (!file_exists($destinationPath)) {
                    mkdir($destinationPath, 0775, true);
                }

                if ($imageFile->move($destinationPath, $fileName)) {
                    $val['image'] = "{$folderName}/{$fileName}";
                }
            }

            $post->update($val);

            return response()->json(['message' => 'Mis a jour', 'data' => $post]);
        } catch (Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function destroy($id)
    {
        try {
            $post = Post::findOrFail($id);
            if ($post->image) {
                $path = storage_path("app/public/{$post->image}");
                if (file_exists($path)) {
                    @unlink($path);
                }
            }

            $post->delete();

            return response()->json(['message' => 'Supprime']);
        } catch (Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    private function isCityActivityPost(Post $post): bool
    {
        return (bool) $this->extractActivityMetadata($post->content);
    }

    private function extractActivityMetadata(?string $content): ?array
    {
        if (!$content) {
            return null;
        }

        $decoded = json_decode($content, true);
        if (!is_array($decoded)) {
            return null;
        }

        if (($decoded['kind'] ?? null) !== 'city-activity') {
            return null;
        }

        $required = ['price', 'currency', 'available_spots', 'schedule', 'departure_point'];
        foreach ($required as $field) {
            if (!array_key_exists($field, $decoded)) {
                return null;
            }
        }

        return $decoded;
    }
}
