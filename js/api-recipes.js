// api-recipes.js
class RecipeAPI {
    constructor() {
        this.baseUrl = 'https://www.themealdb.com/api/json/v1/1';
        // No API key needed for TheMealDB - it's free!
    }

    async searchRecipes(query, options = {}) {
        try {
            // TheMealDB search endpoint
            const response = await fetch(`${this.baseUrl}/search.php?s=${encodeURIComponent(query)}`);
            
            if (!response.ok) {
                throw new Error(`API Error: ${response.status}`);
            }
            
            const data = await response.json();
            
            // Transform TheMealDB response to match our expected format
            const meals = data.meals || [];
            return meals.slice(0, options.limit || 10).map(meal => ({
                id: meal.idMeal,
                title: meal.strMeal,
                readyInMinutes: this.getEstimatedTime(meal.strCategory, meal.strArea),
                image: meal.strMealThumb,
                category: meal.strCategory,
                area: meal.strArea,
                tags: meal.strTags
            }));
            
        } catch (error) {
            console.error('Search error:', error);
            
            // Return demo data if API fails
            return this.getDemoRecipes(query);
        }
    }

    async getRecipeDetails(recipeId) {
        try {
            const response = await fetch(`${this.baseUrl}/lookup.php?i=${recipeId}`);
            
            if (!response.ok) {
                throw new Error(`API Error: ${response.status}`);
            }
            
            const data = await response.json();
            const meal = data.meals ? data.meals[0] : null;
            
            if (!meal) {
                throw new Error('Recipe not found');
            }
            
            return this.transformMealDBRecipe(meal);
            
        } catch (error) {
            console.error('Details error:', error);
            
            // Return demo data if API fails
            return this.getDemoRecipeDetails(recipeId);
        }
    }

    async getRandomRecipes(limit = 5) {
        try {
            const recipes = [];
            
            // TheMealDB doesn't have a batch random endpoint, so we need multiple calls
            for (let i = 0; i < limit; i++) {
                const response = await fetch(`${this.baseUrl}/random.php`);
                if (response.ok) {
                    const data = await response.json();
                    if (data.meals && data.meals[0]) {
                        recipes.push(this.transformMealDBRecipe(data.meals[0]));
                    }
                }
            }
            
            return recipes;
            
        } catch (error) {
            console.error('Random recipes error:', error);
            
            // Return demo data if API fails
            return this.getDemoRandomRecipes(limit);
        }
    }

    // Transform TheMealDB recipe to our format
    transformMealDBRecipe(meal) {
        // Extract ingredients (TheMealDB has strIngredient1 through strIngredient20)
        const ingredients = [];
        for (let i = 1; i <= 20; i++) {
            const ingredient = meal[`strIngredient${i}`];
            const measure = meal[`strMeasure${i}`];
            
            if (ingredient && ingredient.trim() !== '') {
                ingredients.push({
                    name: ingredient,
                    quantity: measure ? measure.trim() : ''
                });
            }
        }

        // Extract instructions (split into steps if possible)
        const instructions = meal.strInstructions;
        
        // Try to parse instructions into steps if they're numbered
        let formattedInstructions = instructions;
        if (!instructions.includes('1.')) {
            // If instructions aren't numbered, split by sentences for better display
            const sentences = instructions.split(/[.!?]+/).filter(s => s.trim().length > 0);
            formattedInstructions = sentences.map((s, index) => `${index + 1}. ${s.trim()}.`).join('\n\n');
        }

        return {
            id: meal.idMeal,
            title: meal.strMeal,
            description: meal.strInstructions ? 
                meal.strInstructions.substring(0, 200) + '...' : 
                `A delicious ${meal.strArea} ${meal.strCategory} dish.`,
            instructions: formattedInstructions,
            prep_time: null, // TheMealDB doesn't provide this
            cook_time: null, // TheMealDB doesn't provide this
            servings: null, // TheMealDB doesn't provide this
            source: 'api',
            source_id: meal.idMeal,
            category: meal.strCategory,
            area: meal.strArea,
            image: meal.strMealThumb,
            tags: meal.strTags,
            youtube: meal.strYoutube,
            ingredients: ingredients
        };
    }

    // Helper to estimate cooking time based on category and cuisine
    getEstimatedTime(category, area) {
        const timeEstimates = {
            'Beef': 60,
            'Chicken': 45,
            'Dessert': 30,
            'Lamb': 70,
            'Pasta': 30,
            'Pork': 55,
            'Seafood': 40,
            'Side': 20,
            'Starter': 25,
            'Vegan': 35,
            'Vegetarian': 35,
            'Breakfast': 20,
            'Goat': 65
        };
        
        // Default times based on cuisine
        const cuisineTimes = {
            'American': 45,
            'British': 50,
            'Canadian': 40,
            'Chinese': 35,
            'Dutch': 45,
            'Egyptian': 55,
            'French': 60,
            'Greek': 45,
            'Indian': 50,
            'Irish': 55,
            'Italian': 40,
            'Jamaican': 50,
            'Japanese': 35,
            'Kenyan': 45,
            'Malaysian': 45,
            'Mexican': 40,
            'Moroccan': 60,
            'Polish': 55,
            'Portuguese': 50,
            'Russian': 55,
            'Spanish': 45,
            'Thai': 35,
            'Tunisian': 55,
            'Turkish': 50,
            'Vietnamese': 40
        };
        
        return timeEstimates[category] || cuisineTimes[area] || 45;
    }

    // Format recipe for database (maintains backward compatibility)
    formatRecipeForDB(apiRecipe) {
        return {
            title: apiRecipe.title || 'Unknown Recipe',
            description: apiRecipe.description || '',
            instructions: apiRecipe.instructions || 'No instructions available.',
            prep_time: apiRecipe.prep_time,
            cook_time: apiRecipe.cook_time,
            servings: apiRecipe.servings,
            source: 'api',
            source_id: apiRecipe.source_id,
            ingredients: apiRecipe.ingredients || []
        };
    }

    // Demo data fallback (keeping your existing demo data)
    getDemoRecipes(query) {
        const demoRecipes = [
            {
                id: '52959',
                title: 'Chicken Tikka Masala',
                readyInMinutes: 50,
                image: 'https://www.themealdb.com/images/media/meals/wyxwsp1486979827.jpg'
            },
            {
                id: '52771',
                title: 'Spicy Arrabiata Penne',
                readyInMinutes: 30,
                image: 'https://www.themealdb.com/images/media/meals/ustsqw1468250014.jpg'
            },
            {
                id: '52893',
                title: 'Apple & Blackberry Crumble',
                readyInMinutes: 45,
                image: 'https://www.themealdb.com/images/media/meals/xvsurr1511719182.jpg'
            },
            {
                id: '52940',
                title: 'Chicken Shawarma',
                readyInMinutes: 60,
                image: 'https://www.themealdb.com/images/media/meals/kcv6hj1598733479.jpg'
            },
            {
                id: '52804',
                title: 'Poutine',
                readyInMinutes: 30,
                image: 'https://www.themealdb.com/images/media/meals/uuyrrx1487327597.jpg'
            }
        ];
        
        // Filter by query if provided
        if (query) {
            const queryLower = query.toLowerCase();
            return demoRecipes.filter(recipe => 
                recipe.title.toLowerCase().includes(queryLower)
            );
        }
        
        return demoRecipes.slice(0, limit || 3);
    }

    getDemoRecipeDetails(recipeId) {
        const demoRecipes = {
            '52959': {
                id: '52959',
                title: 'Chicken Tikka Masala',
                description: 'A delicious Indian curry dish with marinated chicken in a spiced creamy sauce.',
                instructions: '1. Marinate the chicken in yogurt and spices for at least 2 hours.\n\n2. Cook the chicken in a hot pan until charred.\n\n3. Make the sauce with tomatoes, cream, and spices.\n\n4. Combine chicken and sauce, simmer for 10 minutes.\n\n5. Serve with rice or naan bread.',
                prep_time: 20,
                cook_time: 30,
                servings: 4,
                source_id: '52959',
                ingredients: [
                    { name: 'Chicken Breast', quantity: '500g' },
                    { name: 'Yogurt', quantity: '200ml' },
                    { name: 'Tomato Puree', quantity: '400g' },
                    { name: 'Double Cream', quantity: '100ml' },
                    { name: 'Garam Masala', quantity: '2 tbsp' }
                ]
            },
            '52771': {
                id: '52771',
                title: 'Spicy Arrabiata Penne',
                description: 'A classic Italian pasta dish with a spicy tomato sauce.',
                instructions: '1. Cook pasta according to package instructions.\n\n2. Heat oil in a pan, add garlic and chili.\n\n3. Add tomatoes and simmer for 15 minutes.\n\n4. Add basil and season with salt.\n\n5. Toss with pasta and serve with Parmesan.',
                prep_time: 10,
                cook_time: 20,
                servings: 4,
                source_id: '52771',
                ingredients: [
                    { name: 'Penne Pasta', quantity: '400g' },
                    { name: 'Garlic', quantity: '3 cloves' },
                    { name: 'Red Chili', quantity: '2' },
                    { name: 'Tomatoes', quantity: '500g' },
                    { name: 'Fresh Basil', quantity: 'Handful' }
                ]
            }
        };
        
        return demoRecipes[recipeId] || demoRecipes['52959'];
    }

    getDemoRandomRecipes(limit) {
        return this.getDemoRecipes('').slice(0, limit);
    }
}