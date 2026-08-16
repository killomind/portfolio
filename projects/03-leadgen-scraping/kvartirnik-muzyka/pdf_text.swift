import Foundation
import PDFKit

let args = CommandLine.arguments
guard args.count > 1 else { exit(1) }
guard let doc = PDFDocument(url: URL(fileURLWithPath: args[1])) else { exit(1) }
print(doc.string ?? "")
