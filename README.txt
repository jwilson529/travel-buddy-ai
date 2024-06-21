# Travel Buddy AI

Travel Buddy AI is a WordPress plugin that helps users search for rental properties by leveraging OpenAI's API. Users can input their rental preferences, and the plugin will return structured JSON data based on the input.

## Features

- Search for various types of rental properties, including apartments, storage units, and vacation rentals.
- Uses OpenAI API for parsing rental preferences and returning structured data.
- Configurable API key and assistant ID for personalized usage.

## Installation

1. **Download the Plugin**
   - Download the latest version of the plugin from the [releases page](https://github.com/yourusername/travel-buddy-ai/releases).

2. **Install via WordPress Admin**
   - Go to your WordPress admin dashboard.
   - Navigate to `Plugins > Add New`.
   - Click on `Upload Plugin` and select the downloaded ZIP file.
   - Click `Install Now` and then `Activate`.

3. **Configure the Plugin**
   - Go to `Settings > TravelBuddy AI`.
   - Enter your OpenAI API key.
   - (Optional) Enter a custom Assistant ID, or use the default provided.

## Usage

1. **Add the Search Form to Your Site**
   - Use the shortcode `[travelbuddy_search]` to add the search form to any post or page.
   - Example: `echo do_shortcode('[travelbuddy_search]');`

2. **Enter Your Query**
   - Input your rental preferences in the search form and click `Search`.
   - The plugin will display the results in a structured JSON format.

## Settings

### OpenAI API Key

- **Description**: The API key for authenticating with the OpenAI API.
- **Default**: None (must be configured by the user).

### Assistant ID

- **Description**: The ID of the assistant to use for parsing rental preferences.
- **Default**: `asst_lxwL4703F04vWwEpwgu2pKm3`

**Note**: The provided default Assistant ID is configured to handle rental property queries. You can use this ID or set up your own assistant with similar configurations for personalized use.

## Contributing

Contributions are welcome! Please follow these steps to contribute:

1. Fork the repository.
2. Create a new branch with a descriptive name.
3. Make your changes and commit them with clear messages.
4. Push your changes to your fork.
5. Create a pull request with a description of your changes.

## Contact

For inquiries, please contact [OneClickContent](mailto:info@oneclickcontent.com).

---

*This plugin was developed by [OneClickContent](https://oneclickcontent.com).*
